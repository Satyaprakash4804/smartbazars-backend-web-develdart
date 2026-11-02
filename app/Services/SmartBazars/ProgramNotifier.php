<?php

namespace App\Services\SmartBazars;

use App\Models\NetworkMember;
use App\Models\OfflineOrder;
use App\Models\Seller;
use App\Models\User;
use App\Notifications\SmartBazars\ProgramActivityNotification;
use Illuminate\Support\Facades\Log;

/**
 * Sends the in-app + push notifications for POS / pickup / wallet / earnings events.
 * Never throws: a notification problem must not break a sale or a payout.
 */
class ProgramNotifier
{
    public static function user(?User $user, string $title, string $message, string $type, string $sentTo, array $meta = []): void
    {
        if (!$user) return;
        try {
            $user->notify(new ProgramActivityNotification($title, $message, $type, $sentTo, $meta));
        } catch (\Throwable $e) {
            Log::warning('Program notification failed: ' . $e->getMessage(), ['user_id' => $user->id, 'title' => $title]);
        }
    }

    public static function seller(?Seller $seller, string $title, string $message, string $type = 'order_update', array $meta = []): void
    {
        self::user($seller?->user, $title, $message, $type, 'seller', $meta);
    }

    public static function member(?NetworkMember $member, string $title, string $message, string $type = 'wallet_transaction', array $meta = []): void
    {
        self::user($member?->user, $title, $message, $type, 'partner', $meta);
    }

    public static function admins(string $title, string $message, string $type = 'system', array $meta = []): void
    {
        try {
            User::where('access_panel', 'admin')->limit(25)->get()
                ->each(fn($u) => self::user($u, $title, $message, $type, 'admin', $meta));
        } catch (\Throwable $e) {
            Log::warning('Admin notification failed: ' . $e->getMessage());
        }
    }

    // ---- offline (User Card) orders ----------------------------------------------------------

    /** A BJO placed an order for a card holder: tell the vendor, the customer and admin. */
    public static function offlineOrderPlaced(OfflineOrder $o): void
    {
        $o->loadMissing(['customer', 'seller.user', 'member.user']);
        $meta = ['offline_order_id' => $o->id, 'order_no' => $o->order_no, 'channel' => $o->channel];
        $amount = '₹' . number_format((float)$o->total, 2);

        if ($o->channel === 'worker') {
            self::seller($o->seller, 'New offline order ' . $o->order_no,
                "BJO {$o->member?->user?->name} placed an order for {$o->customer?->name} ({$amount}). Keep it ready — the customer will pick it up.",
                'new_order', $meta);
            self::user($o->customer, 'Your order is placed',
                "Order {$o->order_no} ({$amount}) is ready for pickup at {$o->seller?->displayName()}. Show your User Card to collect it.",
                'order_update', 'customer', $meta);
        } else {
            self::seller($o->seller, 'Offline sale ' . $o->order_no, "Counter sale {$amount} recorded for {$o->customer?->name}.", 'new_order', $meta);
        }
    }

    /** Goods handed over: sale is complete. */
    public static function offlineOrderCompleted(OfflineOrder $o): void
    {
        $o->loadMissing(['customer', 'seller.user', 'member.user']);
        $meta = ['offline_order_id' => $o->id, 'order_no' => $o->order_no];
        $coins = $o->coins_earned ? " You earned {$o->coins_earned} cashback coins." : '';
        self::user($o->customer, 'Order completed', "Order {$o->order_no} was handed over.{$coins}", 'order_update', 'customer', $meta);
        if ($o->channel === 'worker') {
            self::member($o->member, 'Order handed over', "Order {$o->order_no} for {$o->customer?->name} was collected from {$o->seller?->displayName()}.", 'order_update', $meta);
        }
    }

    public static function offlineOrderCancelled(OfflineOrder $o): void
    {
        $o->loadMissing(['customer', 'seller.user', 'member.user']);
        $meta = ['offline_order_id' => $o->id, 'order_no' => $o->order_no];
        $msg = "Order {$o->order_no} was cancelled. {$o->cancel_reason}";
        self::user($o->customer, 'Order cancelled', $msg, 'order_update', 'customer', $meta);
        self::seller($o->seller, 'Order cancelled', $msg, 'order_update', $meta);
        if ($o->channel === 'worker') self::member($o->member, 'Order cancelled — wallet refunded', $msg, 'wallet_transaction', $meta);
    }

    // ---- earnings -----------------------------------------------------------------------------

    public static function earning(NetworkMember $member, float $amount, string $desc): void
    {
        self::member($member, 'You earned ₹' . number_format($amount, 2), $desc, 'wallet_transaction', ['amount' => $amount]);
    }

    public static function vendorEarning(Seller $seller, float $amount, string $desc): void
    {
        self::seller($seller, 'Commission credited ₹' . number_format($amount, 2), $desc, 'payment', ['amount' => $amount]);
    }
}
