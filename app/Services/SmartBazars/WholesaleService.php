<?php

namespace App\Services\SmartBazars;

use App\Enums\Seller\SellerSettlementTypeEnum;
use App\Models\Seller;
use App\Models\WholesaleOrder;
use App\Models\WholesaleProduct;
use App\Services\SellerStatementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Vendors buy stock in bulk from SmartBazars admin (vendor price, min/max qty, pay partly with coins). */
class WholesaleService
{
    public function __construct(protected VendorCoinService $coins, protected SellerStatementService $statements) {}

    /**
     * @param array<int,int> $qty  wholesale_product_id => quantity
     */
    public function placeOrder(Seller $seller, array $qty, int $useCoins, string $paymentMethod, ?string $note = null): WholesaleOrder
    {
        $qty = array_filter(array_map('intval', $qty), fn($q) => $q > 0);
        if (!$qty) {
            throw ValidationException::withMessages(['items' => 'Add at least one product.']);
        }
        if (!in_array($paymentMethod, ['settlement', 'offline'], true)) {
            $paymentMethod = 'settlement';
        }

        return DB::transaction(function () use ($seller, $qty, $useCoins, $paymentMethod, $note) {
            $products = WholesaleProduct::with('storeVariant')->whereIn('id', array_keys($qty))->where('is_active', true)->lockForUpdate()->get()->keyBy('id');
            $lines = [];
            $subtotal = 0.0;
            $coinCap = 0.0;
            foreach ($qty as $id => $q) {
                $p = $products[$id] ?? null;
                if (!$p) {
                    throw ValidationException::withMessages(['items' => 'A product is no longer available.']);
                }
                if ($q < $p->min_qty) {
                    throw ValidationException::withMessages(['items' => "{$p->title}: minimum order is {$p->min_qty}."]);
                }
                if ($p->max_qty && $q > $p->max_qty) {
                    throw ValidationException::withMessages(['items' => "{$p->title}: maximum per order is {$p->max_qty}."]);
                }
                $available = $p->available_stock; // own-store items share the own store's stock
                if ($available !== null && $q > $available) {
                    throw ValidationException::withMessages(['items' => "{$p->title}: only {$available} in stock."]);
                }
                $line = round((float)$p->vendor_price * $q, 2);
                $subtotal += $line;
                $coinCap += $line * min(100, (int)$p->max_coin_percent) / 100;
                $lines[] = ['p' => $p, 'q' => $q, 'line' => $line];
            }

            $s = Seller::whereKey($seller->id)->lockForUpdate()->first();
            $value = $this->coins->coinValue();
            $maxCoins = (int)min($s->coin_balance, floor($coinCap / $value), floor($subtotal / $value));
            $coins = max(0, min($useCoins, $maxCoins));
            $coinValue = round($coins * $value, 2);
            $payable = round($subtotal - $coinValue, 2);

            $order = WholesaleOrder::create([
                'seller_id' => $s->id,
                'subtotal' => $subtotal,
                'coins_used' => $coins,
                'coin_value' => $coinValue,
                'amount_payable' => $payable,
                'payment_method' => $paymentMethod,
                'status' => 'placed',
                'vendor_note' => $note,
            ]);
            foreach ($lines as $l) {
                $order->items()->create([
                    'wholesale_product_id' => $l['p']->id,
                    'title' => $l['p']->title,
                    'unit' => $l['p']->unit,
                    'price' => $l['p']->vendor_price,
                    'quantity' => $l['q'],
                    'line_total' => $l['line'],
                ]);
                if ($l['p']->storeVariant) {
                    $l['p']->storeVariant->decrement('stock', $l['q']);
                } elseif ($l['p']->stock !== null) {
                    $l['p']->decrement('stock', $l['q']);
                }
            }
            $this->coins->payWholesale($s, $coins, $order->id);
            if ($paymentMethod === 'settlement' && $payable > 0) {
                $this->statements->addEntry([
                    'seller_id' => $s->id,
                    'entry_type' => SellerSettlementTypeEnum::DEBIT(),
                    'amount' => $payable,
                    'currency_code' => 'INR',
                    'reference_type' => 'wholesale_order',
                    'reference_id' => (string)$order->id,
                    'description' => "Bulk purchase #{$order->id} from SmartBazars",
                ]);
            }
            return $order;
        });
    }

    public function updateStatus(WholesaleOrder $order, string $status, ?string $note = null): void
    {
        if (!in_array($status, WholesaleOrder::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Invalid status']);
        }
        if ($order->status === 'cancelled') {
            throw ValidationException::withMessages(['status' => 'Order already cancelled.']);
        }
        DB::transaction(function () use ($order, $status, $note) {
            if ($status === 'cancelled') {
                $seller = Seller::find($order->seller_id);
                $this->coins->refundWholesale($seller, (int)$order->coins_used, $order->id);
                foreach ($order->items as $it) {
                    if ($it->product && $it->product->storeVariant) {
                        $it->product->storeVariant->increment('stock', $it->quantity);
                    } elseif ($it->product && $it->product->stock !== null) {
                        $it->product->increment('stock', $it->quantity);
                    }
                }
                if ($order->payment_method === 'settlement' && (float)$order->amount_payable > 0) {
                    $this->statements->addEntry([
                        'seller_id' => $order->seller_id,
                        'entry_type' => SellerSettlementTypeEnum::CREDIT(),
                        'amount' => $order->amount_payable,
                        'currency_code' => 'INR',
                        'reference_type' => 'wholesale_order_cancel',
                        'reference_id' => (string)$order->id,
                        'description' => "Bulk purchase #{$order->id} cancelled — amount returned",
                    ]);
                }
            }
            $order->update(['status' => $status, 'admin_note' => $note ?? $order->admin_note]);
        });
    }
}
