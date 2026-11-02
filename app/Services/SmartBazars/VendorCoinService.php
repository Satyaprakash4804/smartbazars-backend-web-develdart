<?php

namespace App\Services\SmartBazars;

use App\Enums\Seller\SellerSettlementTypeEnum;
use App\Models\CoinTransfer;
use App\Models\OrderItem;
use App\Models\Seller;
use App\Models\VendorCoinTransaction;
use App\Services\SellerStatementService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Where redeemed customer coins go:
 *   customer pays part of an item with coins  ->  when the item is DELIVERED those coins are
 *   credited to the vendor who sold it (or to SmartBazars admin for own-store products).
 *   Vendor "transfers" coins to admin  ->  admin approves  ->  vendor gets ₹ in their settlement.
 *   Vendors can also spend coins on bulk (wholesale) purchases from admin.
 * Admin coin balance = sum of ledger rows with seller_id = NULL.
 */
class VendorCoinService
{
    public function __construct(protected SellerStatementService $statements) {}

    public function coinValue(): float
    {
        return max(0.01, (float)ProgramSettings::get('coin_value', 1));
    }

    public function adminBalance(): int
    {
        return (int)VendorCoinTransaction::whereNull('seller_id')->sum('coins');
    }

    /** Called when an order item is delivered. */
    public function creditForDeliveredItem(OrderItem $item): void
    {
        $coins = (int)round((float)$item->coin_discount / $this->coinValue());
        if ($coins <= 0) return;
        $seller = $item->store?->seller;
        $toAdmin = !$seller || $seller->is_house_store;
        $this->post($toAdmin ? null : $seller, 'earn', $coins,
            "Coins paid by customer on Order #{$item->order_id} ({$item->title})", ['order_item_id' => $item->id]);
    }

    /** Offline (User Card) orders: coins the customer spent go to the vendor (or SmartBazars admin for the house store). */
    public function creditForOfflineOrder(\App\Models\OfflineOrder $o): void
    {
        $coins = (int)$o->coins_used;
        if ($coins <= 0) return;
        if (VendorCoinTransaction::where('type', 'earn')->where('offline_order_id', $o->id)->exists()) return;
        $seller = Seller::find($o->seller_id);
        $toAdmin = !$seller || $seller->is_house_store;
        $this->post($toAdmin ? null : $seller, 'earn', $coins,
            "Coins paid by customer on offline order {$o->order_no}", ['offline_order_id' => $o->id]);
    }

    public function reverseForOfflineOrder(\App\Models\OfflineOrder $o): void
    {
        $earn = VendorCoinTransaction::where('type', 'earn')->where('offline_order_id', $o->id)->first();
        if (!$earn || VendorCoinTransaction::where('type', 'reverse')->where('offline_order_id', $o->id)->exists()) return;
        $seller = $earn->seller_id ? Seller::find($earn->seller_id) : null;
        $this->post($seller, 'reverse', -$earn->coins, "Coins reversed — offline order {$o->order_no} cancelled", ['offline_order_id' => $o->id]);
    }

    /** Called when a delivered item is returned/refunded. */
    public function reverseForItem(OrderItem $item): void
    {
        $earn = VendorCoinTransaction::where('type', 'earn')->where('order_item_id', $item->id)->first();
        if (!$earn) return;
        $seller = $earn->seller_id ? Seller::find($earn->seller_id) : null;
        $this->post($seller, 'reverse', -$earn->coins, "Coins reversed — item #{$item->id} returned", ['order_item_id' => $item->id]);
    }

    public function requestTransfer(Seller $seller, int $coins, ?string $note = null): CoinTransfer
    {
        if ($coins <= 0) {
            throw ValidationException::withMessages(['coins' => 'Enter the number of coins to transfer.']);
        }
        return DB::transaction(function () use ($seller, $coins, $note) {
            $s = Seller::whereKey($seller->id)->lockForUpdate()->first();
            if ($s->coin_balance < $coins) {
                throw ValidationException::withMessages(['coins' => 'You only have ' . $s->coin_balance . ' coins.']);
            }
            $t = CoinTransfer::create([
                'seller_id' => $s->id,
                'coins' => $coins,
                'rupee_value' => round($coins * $this->coinValue(), 2),
                'status' => 'pending',
                'vendor_note' => $note,
            ]);
            $this->post($s, 'transfer_out', -$coins, "Transfer #{$t->id} to SmartBazars (awaiting approval)", ['coin_transfer_id' => $t->id]);
            return $t;
        });
    }

    public function processTransfer(CoinTransfer $t, bool $approve, ?string $note = null): void
    {
        if ($t->status !== 'pending') {
            throw ValidationException::withMessages(['transfer' => 'Already processed.']);
        }
        DB::transaction(function () use ($t, $approve, $note) {
            $seller = Seller::find($t->seller_id);
            if ($approve) {
                $this->post(null, 'transfer_in', $t->coins, "Coins received from vendor #{$t->seller_id} (transfer #{$t->id})", ['coin_transfer_id' => $t->id]);
                // vendor gets the rupee value in their settlement statement
                $this->statements->addEntry([
                    'seller_id' => $t->seller_id,
                    'entry_type' => SellerSettlementTypeEnum::CREDIT(),
                    'amount' => $t->rupee_value,
                    'currency_code' => 'INR',
                    'reference_type' => 'coin_transfer',
                    'reference_id' => (string)$t->id,
                    'description' => "{$t->coins} coins converted to cash (transfer #{$t->id})",
                ]);
            } else {
                $this->post($seller, 'transfer_refund', $t->coins, "Transfer #{$t->id} rejected — coins returned", ['coin_transfer_id' => $t->id]);
            }
            $t->update(['status' => $approve ? 'approved' : 'rejected', 'admin_note' => $note, 'processed_at' => now()]);
        });
    }

    /** Vendor pays part of a wholesale order with coins: coins move vendor -> admin. */
    public function payWholesale(Seller $seller, int $coins, int $orderId): void
    {
        if ($coins <= 0) return;
        $this->post($seller, 'wholesale_payment', -$coins, "Used on bulk order #{$orderId}", ['wholesale_order_id' => $orderId]);
        $this->post(null, 'wholesale_payment', $coins, "Received on bulk order #{$orderId} from vendor #{$seller->id}", ['wholesale_order_id' => $orderId]);
    }

    public function refundWholesale(Seller $seller, int $coins, int $orderId): void
    {
        if ($coins <= 0) return;
        $this->post(null, 'adjust', -$coins, "Bulk order #{$orderId} cancelled — coins returned to vendor", ['wholesale_order_id' => $orderId]);
        $this->post($seller, 'adjust', $coins, "Bulk order #{$orderId} cancelled — coins returned", ['wholesale_order_id' => $orderId]);
    }

    public function adjust(?Seller $seller, int $coins, string $reason): void
    {
        $this->post($seller, 'adjust', $coins, $reason);
    }

    /** One ledger row + balance update (vendor) — admin balance is the ledger sum. */
    protected function post(?Seller $seller, string $type, int $coins, string $desc, array $refs = []): void
    {
        try {
            DB::transaction(function () use ($seller, $type, $coins, $desc, $refs) {
                if ($seller) {
                    $s = Seller::whereKey($seller->id)->lockForUpdate()->first();
                    $new = max(0, (int)$s->coin_balance + $coins);
                    $s->forceFill(['coin_balance' => $new])->saveQuietly();
                } else {
                    $new = $this->adminBalance() + $coins;
                }
                VendorCoinTransaction::create(array_merge([
                    'seller_id' => $seller?->id,
                    'type' => $type,
                    'coins' => $coins,
                    'balance_after' => $new,
                    'description' => $desc,
                ], $refs));
            });
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) != 1062) throw $e; // already posted for this item
        }
    }
}
