<?php

namespace App\Services\SmartBazars;

use App\Models\CoinTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cashback coins.
 *  - Admin sets `products.cashback_coins` (coins per unit) on any product (own or vendor).
 *  - The value is snapshotted on the order item (coins × qty) when the order is placed.
 *  - Coins are credited when the item is DELIVERED, and reversed if it is later returned.
 *  - Coins can be redeemed on the next order: 1 coin = ProgramSettings::coin_value rupees (default ₹1).
 */
class CoinService
{
    public function enabled(): bool
    {
        return (bool)ProgramSettings::get('coins_enabled', true);
    }

    public function coinValue(): float
    {
        return max(0.01, (float)ProgramSettings::get('coin_value', 1));
    }

    /** How many coins can be applied to a payable amount. */
    public function preview(User $user, float $payable): array
    {
        $balance = (int)$user->coin_balance;
        if (!$this->enabled() || $balance <= 0 || $payable <= 0) {
            return ['coin_balance' => $balance, 'coins_used' => 0, 'coin_discount' => 0.0];
        }
        $maxAmount = $payable * min(100, max(0, (float)ProgramSettings::get('max_coin_redeem_percent', 100))) / 100;
        $coins = (int)min($balance, floor($maxAmount / $this->coinValue()));
        return ['coin_balance' => $balance, 'coins_used' => $coins, 'coin_discount' => round($coins * $this->coinValue(), 2)];
    }

    /** Called inside the order-creation DB transaction, after order items exist. */
    public function redeemForOrder(Order $order): void
    {
        $coins = (int)$order->coins_used;
        if ($coins <= 0) {
            return;
        }
        $user = User::whereKey($order->user_id)->lockForUpdate()->firstOrFail();
        if ($user->coin_balance < $coins) {
            throw ValidationException::withMessages(['use_coins' => 'Not enough coins.']);
        }
        $user->decrement('coin_balance', $coins);
        CoinTransaction::create([
            'user_id' => $user->id,
            'type' => 'redeem',
            'coins' => -$coins,
            'balance_after' => $user->coin_balance,
            'order_id' => $order->id,
            'description' => "Used on Order #{$order->id}",
        ]);

        // Allocate the rupee discount across items (so refunds give back the right coins)
        $items = $order->items()->get();
        $total = (float)$items->sum('subtotal');
        $discount = (float)$order->coin_discount;
        $allocated = 0.0;
        foreach ($items->values() as $i => $item) {
            $share = $i === $items->count() - 1
                ? round($discount - $allocated, 2)
                : ($total > 0 ? round($discount * $item->subtotal / $total, 2) : 0);
            $allocated += $share;
            $item->update(['coin_discount' => $share]);
        }
    }

    public function awardForDeliveredItem(OrderItem $item): void
    {
        $coins = (int)$item->cashback_coins;
        if ($coins <= 0 || !$this->enabled()) {
            return;
        }
        $this->post($item->order->user_id, 'earn', $coins, $item, "Cashback for Order #{$item->order_id} ({$item->title})");
    }

    /** Called when an item is cancelled / rejected / returned. */
    public function onItemRefunded(OrderItem $item): void
    {
        // 1) give back coins that were spent on this item
        $spent = (float)$item->coin_discount;
        if ($spent > 0) {
            $coins = (int)round($spent / $this->coinValue());
            $this->post($item->order->user_id, 'refund', $coins, $item, "Coins returned for item #{$item->id}");
        }
        // 2) take back cashback that was already earned on it
        $earned = CoinTransaction::where('type', 'earn')->where('order_item_id', $item->id)->first();
        if ($earned) {
            $this->post($item->order->user_id, 'reverse', -$earned->coins, $item, "Cashback reversed for returned item #{$item->id}");
        }
    }

    public function adminAdjust(User $user, int $coins, string $reason): void
    {
        if ($coins === 0) return;
        DB::transaction(function () use ($user, $coins, $reason) {
            $u = User::whereKey($user->id)->lockForUpdate()->first();
            $new = max(0, $u->coin_balance + $coins);
            $u->update(['coin_balance' => $new]);
            CoinTransaction::create(['user_id' => $u->id, 'type' => 'adjust', 'coins' => $coins, 'balance_after' => $new, 'description' => $reason]);
        });
    }

    protected function post(int $userId, string $type, int $coins, OrderItem $item, string $desc): void
    {
        if ($coins === 0) return;
        try {
            DB::transaction(function () use ($userId, $type, $coins, $item, $desc) {
                $u = User::whereKey($userId)->lockForUpdate()->first();
                $new = max(0, (int)$u->coin_balance + $coins);
                CoinTransaction::create([
                    'user_id' => $userId,
                    'type' => $type,
                    'coins' => $coins,
                    'balance_after' => $new,
                    'order_id' => $item->order_id,
                    'order_item_id' => $item->id,
                    'description' => $desc,
                ]);
                $u->update(['coin_balance' => $new]);
            });
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) != 1062) throw $e; // already posted for this item
        }
    }
}
