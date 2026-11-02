<?php

namespace App\Services\SmartBazars;

use App\Models\CoinTransaction;
use App\Models\NetworkMember;
use App\Models\NetworkWalletLedger;
use App\Models\OfflineCustomerProfile;
use App\Models\OfflineOrder;
use App\Models\OfflineOrderItem;
use App\Models\Order;
use App\Models\Seller;
use App\Models\StoreProductVariant;
use App\Models\User;
use App\Models\UserCard;
use App\Models\WelcomeKit;
use App\Models\WelcomeKitPurchase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Offline sales (POS).
 *  - Vendor counter sale ......... paid at the counter (cash/UPI/card) and/or with the card's cashback coins, completed at once.
 *  - BJO (worker) order .......... paid from the BJO wallet and/or the card's coins, stays "ready for pickup" until the vendor hands over.
 * Offline purchases never carry a delivery charge: total = items - coin discount (+ Welcome Kit if bought).
 * Commissions (vendor-sale + repurchase), cashback coins and vendor coins follow the same rules as online orders and are
 * posted when the order is COMPLETED. The Welcome Kit can be added as a line for card holders who have not bought it yet.
 */
class OfflinePosService
{
    public function __construct(protected ReferralService $referrals) {}

    // ------------------------------------------------------------------ lookup

    /** Accepts the QR payload, a raw/formatted card number or the customer's mobile number. */
    public function findCard(string $input): ?UserCard
    {
        // Works for the multi-line QR (details + "SMARTBAZARS:CARD:<no>"), the old QR and typed numbers
        $compact = UserCard::cardNumberFromScan($input);
        if ($compact === '') return null;

        $card = UserCard::with('user')->where('card_number', $compact)->first();
        if ($card) return $card;

        $digits = preg_replace('/\D/', '', str_contains($input, "\n") ? '' : $input);
        if (strlen($digits) >= 10) {
            $mobile = substr($digits, -10);
            $user = User::where('mobile', $mobile)->first();
            return $user ? UserCard::with('user')->where('user_id', $user->id)->first() : null;
        }
        return null;
    }

    public function customerSummary(UserCard $card): array
    {
        $card->loadMissing('user');
        $user = $card->user;
        $profile = OfflineCustomerProfile::with(['seller', 'member.user'])->where('user_id', $user->id)->first();
        $trail = $this->referrals->trailFor($user);

        $offline = OfflineOrder::with(['items', 'seller', 'member.user'])->where('user_id', $user->id)->latest()->limit(100)->get();
        $online = Order::where('user_id', $user->id)->latest()->limit(50)->get();

        return [
            'card' => $card,
            'user' => $user,
            'profile' => $profile,
            'valid' => $card->status === 'active' && $card->valid_upto?->endOfDay()->isFuture(),
            'coins' => (int)$user->coin_balance,
            'kit' => $this->kitInfo($user),
            'coin_value' => app(CoinService::class)->coinValue(),
            'trail' => [
                'BJO (added by)' => $trail['worker']?->user?->name,
                'Vendor' => $trail['vendor']?->displayName(),
                'BSO (vendor\'s officer)' => $trail['agent']?->user?->name,
                'BSO upline' => $trail['upline']?->user?->name,
            ],
            'dates' => [
                'Customer since' => $user->created_at?->format('d M Y, h:i A'),
                'Card issued' => $card->issued_at?->format('d M Y'),
                'Card valid upto' => $card->valid_upto?->format('d M Y'),
                'First purchase' => $offline->last()?->created_at?->format('d M Y'),
                'Last purchase' => $offline->first()?->created_at?->format('d M Y'),
            ],
            'offline_orders' => $offline,
            'online_orders' => $online,
            'totals' => [
                'offline_orders' => $offline->where('status', '!=', 'cancelled')->count(),
                'offline_spent' => (float)$offline->where('status', '!=', 'cancelled')->sum('total'),
                'online_orders' => $online->count(),
                'coins_earned' => (int)CoinTransaction::where('user_id', $user->id)->where('coins', '>', 0)->sum('coins'),
                'coins_used' => (int)abs(CoinTransaction::where('user_id', $user->id)->where('type', 'redeem')->sum('coins')),
            ],
            'coin_history' => CoinTransaction::where('user_id', $user->id)->latest()->limit(30)->get(),
        ];
    }

    /** Welcome-kit status for the POS / lookup screens: recommend it on top for card holders who do not have it. */
    public function kitInfo(User $user): array
    {
        $kit = WelcomeKit::active();
        $has = app(WelcomeKitService::class)->hasActivePurchase($user);
        $pending = OfflineOrderItem::where('is_welcome_kit', true)
            ->whereHas('order', fn($q) => $q->where('user_id', $user->id)->where('status', 'pending_pickup'))->exists();
        $firstPurchase = !OfflineOrder::where('user_id', $user->id)->where('status', '!=', 'cancelled')->exists();
        return [
            'has' => $has,
            'available' => (bool)$kit && !$has && !$pending,
            'pending' => $pending,
            'recommend' => (bool)$kit && !$has && !$pending,
            'first_purchase' => $firstPurchase,
            'name' => $kit?->name,
            'price' => $kit ? (float)$kit->price : null,
            'items' => $kit ? $kit->items->map(fn($i) => ($i->item_name ?? '') . ' × ' . $i->quantity)->values()->all() : [],
        ];
    }

    // ------------------------------------------------------------------ catalogue

    public function searchProducts(Seller $seller, string $q = '', int $limit = 24): Collection
    {
        $rows = DB::table('store_product_variants as spv')
            ->join('stores as st', 'st.id', '=', 'spv.store_id')
            ->join('product_variants as pv', 'pv.id', '=', 'spv.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('st.seller_id', $seller->id)
            ->whereNull('spv.deleted_at')
            ->when($q !== '', fn($w) => $w->where(fn($x) => $x->where('p.title', 'like', "%$q%")
                ->orWhere('pv.title', 'like', "%$q%")->orWhere('spv.sku', 'like', "%$q%")->orWhere('pv.barcode', $q)))
            ->orderBy('p.title')
            ->limit($limit)
            ->get(['spv.id as id', 'p.id as product_id', 'p.title as product_title', 'pv.title as variant_title',
                'spv.sku', 'spv.stock', 'p.cashback_coins']);

        // price goes through the model accessor so tax handling matches the customer apps
        return $rows->map(function ($r) {
            $spv = StoreProductVariant::find($r->id);
            $special = (float)($spv->special_price ?? 0);
            $price = $special > 0 ? $special : (float)$spv->price;
            $title = $r->product_title . ($r->variant_title && $r->variant_title !== $r->product_title ? ' — ' . $r->variant_title : '');
            return [
                'id' => (int)$r->id, 'product_id' => (int)$r->product_id, 'title' => $title, 'sku' => $r->sku,
                'price' => round($price, 2), 'stock' => (int)$r->stock, 'cashback_coins' => (int)$r->cashback_coins,
            ];
        });
    }

    // ------------------------------------------------------------------ orders

    /** Vendor POS: sell over the counter to a card holder. */
    public function placeCounterSale(Seller $seller, UserCard $card, array $items, array $pay, ?int $byUserId = null): OfflineOrder
    {
        $o = $this->place($seller, $card, $items, $pay, 'counter', null, $byUserId);
        ProgramNotifier::offlineOrderPlaced($o);
        ProgramNotifier::offlineOrderCompleted($o);
        return $o;
    }

    /** BJO orders for a card holder from the vendor they work for; goods are collected at the vendor. */
    public function placeWorkerOrder(NetworkMember $bjo, UserCard $card, array $items, array $pay): OfflineOrder
    {
        abort_unless($bjo->isWorker() && $bjo->seller_id, 403);
        $seller = Seller::findOrFail($bjo->seller_id);
        $o = $this->place($seller, $card, $items, $pay, 'worker', $bjo, $bjo->user_id);
        ProgramNotifier::offlineOrderPlaced($o);     // vendor + customer are told: goods are waiting for pickup
        return $o;
    }

    protected function place(Seller $seller, UserCard $card, array $items, array $pay, string $channel, ?NetworkMember $bjo, ?int $byUserId): OfflineOrder
    {
        if ($card->status !== 'active' || !$card->valid_upto?->endOfDay()->isFuture()) {
            throw ValidationException::withMessages(['card' => 'This User Card is blocked or expired.']);
        }
        $lines = collect($items)->filter(fn($i) => (int)($i['id'] ?? 0) > 0 && (int)($i['qty'] ?? 0) > 0)
            ->groupBy('id')->map(fn($g, $id) => ['id' => (int)$id, 'qty' => (int)$g->sum('qty')])->values();
        if ($lines->isEmpty() && empty($pay['welcome_kit'])) {
            throw ValidationException::withMessages(['items' => 'Add at least one product.']);
        }

        return DB::transaction(function () use ($seller, $card, $lines, $pay, $channel, $bjo, $byUserId) {
            $user = User::whereKey($card->user_id)->lockForUpdate()->firstOrFail();

            // ---- price + stock (rows locked so two sales cannot oversell) ----
            $subtotal = 0.0;
            $built = [];
            foreach ($lines as $l) {
                $spv = StoreProductVariant::with(['productVariant.product', 'store'])->whereKey($l['id'])->lockForUpdate()->first();
                if (!$spv || (int)$spv->store?->seller_id !== (int)$seller->id) {
                    throw ValidationException::withMessages(['items' => 'A selected product does not belong to this vendor.']);
                }
                if ((int)$spv->stock < $l['qty']) {
                    $name = $spv->productVariant?->product?->title ?? 'Product';
                    throw ValidationException::withMessages(['items' => "Only {$spv->stock} in stock for {$name}."]);
                }
                $special = (float)($spv->special_price ?? 0);
                $unit = round($special > 0 ? $special : (float)$spv->price, 2);
                $product = $spv->productVariant?->product;
                $vt = $spv->productVariant?->title;
                $title = ($product->title ?? 'Product') . ($vt && $vt !== ($product->title ?? '') ? ' — ' . $vt : '');
                $commissionPct = (float)($spv->category_commission->commission ?? 0);
                $built[] = [
                    'spv' => $spv, 'unit' => $unit, 'qty' => $l['qty'], 'sub' => round($unit * $l['qty'], 2),
                    'admin_commission' => round(round($unit * $l['qty'], 2) * $commissionPct / 100, 2),
                    'title' => $title, 'coins' => (int)($product->cashback_coins ?? 0) * $l['qty'],
                    'product_id' => $product->id ?? null,
                ];
                $subtotal += round($unit * $l['qty'], 2);
            }
            $subtotal = round($subtotal, 2);

            // ---- optional Welcome Kit (never discounted by coins; has its own commission) ----
            $kit = null;
            $kitAmount = 0.0;
            if (!empty($pay['welcome_kit'])) {
                $info = $this->kitInfo($user);
                if ($info['has']) {
                    throw ValidationException::withMessages(['welcome_kit' => 'This customer already has the Welcome Kit.']);
                }
                if ($info['pending']) {
                    throw ValidationException::withMessages(['welcome_kit' => 'A Welcome Kit is already waiting for pickup for this customer.']);
                }
                $kit = WelcomeKit::active();
                if (!$kit) {
                    throw ValidationException::withMessages(['welcome_kit' => 'Welcome Kit is not available right now.']);
                }
                $kitAmount = round((float)$kit->price, 2);
            }

            // ---- coins from the card (goods only) ----
            $coins = $this->coinsToUse($user, $subtotal, $pay);
            $coinDiscount = round($coins * app(CoinService::class)->coinValue(), 2);
            $total = round(max(0, $subtotal - $coinDiscount) + $kitAmount, 2);

            // ---- how the rest is paid ----
            $walletPaid = 0.0;
            $cashPaid = 0.0;
            $mode = 'cash';
            if ($channel === 'worker') {
                $walletPaid = $total;                         // BJO pays whatever coins did not cover from the BJO wallet
                $mode = $coins > 0 && $walletPaid > 0 ? 'mixed' : ($coins > 0 ? 'coins' : 'wallet');
            } else {
                $cashPaid = $total;
                $m = $pay['payment_mode'] ?? 'cash';
                $m = in_array($m, ['cash', 'upi', 'card'], true) ? $m : 'cash';
                $mode = $coins > 0 && $cashPaid > 0 ? 'mixed' : ($coins > 0 ? 'coins' : $m);
            }

            $order = OfflineOrder::create([
                'order_no' => $this->nextOrderNo(),
                'user_id' => $user->id,
                'user_card_id' => $card->id,
                'seller_id' => $seller->id,
                'channel' => $channel,
                'network_member_id' => $bjo?->id,
                'placed_by_user_id' => $byUserId,
                'status' => $channel === 'worker' ? 'pending_pickup' : 'completed',
                'payment_mode' => $mode,
                'subtotal' => $subtotal,
                'kit_amount' => $kitAmount,
                'delivery_charge' => 0,
                'coins_used' => $coins,
                'coin_discount' => $coinDiscount,
                'wallet_paid' => $walletPaid,
                'cash_paid' => $cashPaid,
                'total' => $total,
                'coins_earned' => collect($built)->sum('coins'),
                'note' => $pay['note'] ?? null,
                'handed_over_at' => $channel === 'counter' ? now() : null,
                'handed_over_by' => $channel === 'counter' ? $byUserId : null,
            ]);

            foreach ($built as $b) {
                OfflineOrderItem::create([
                    'offline_order_id' => $order->id,
                    'store_product_variant_id' => $b['spv']->id,
                    'product_id' => $b['product_id'],
                    'title' => $b['title'],
                    'sku' => $b['spv']->sku,
                    'quantity' => $b['qty'],
                    'unit_price' => $b['unit'],
                    'subtotal' => $b['sub'],
                    'admin_commission_amount' => $b['admin_commission'],
                    'cashback_coins' => $b['coins'],
                ]);
                $b['spv']->decrement('stock', $b['qty']);
            }
            if ($kit) {
                OfflineOrderItem::create([
                    'offline_order_id' => $order->id, 'title' => 'SmartBazars Welcome Kit', 'quantity' => 1,
                    'unit_price' => $kitAmount, 'subtotal' => $kitAmount, 'is_welcome_kit' => true, 'welcome_kit_id' => $kit->id,
                ]);
            }

            if ($coins > 0) {
                $this->postCoins($user, 'redeem', -$coins, $order, "Used on offline order {$order->order_no}");
            }
            if ($walletPaid > 0) {
                $this->debitBjoWallet($bjo, $walletPaid, $order);
            }
            if ($channel === 'counter') {
                $this->completeOrder($order);              // counter sale is complete immediately
            }
            return $order->fresh(['items', 'customer', 'card', 'seller', 'member.user']);
        });
    }

    /** Vendor gives the goods to the customer for a BJO order. */
    public function handOver(OfflineOrder $o, ?int $byUserId = null): OfflineOrder
    {
        $done = DB::transaction(function () use ($o, $byUserId) {
            $o = OfflineOrder::whereKey($o->id)->lockForUpdate()->firstOrFail();
            if ($o->status !== 'pending_pickup') {
                throw ValidationException::withMessages(['order' => 'This order is not waiting for pickup.']);
            }
            $o->update(['status' => 'completed', 'handed_over_at' => now(), 'handed_over_by' => $byUserId]);
            $this->completeOrder($o);

            // the vendor receives what the BJO paid from the wallet for the goods (the Welcome Kit amount belongs to SmartBazars)
            $vendorShare = round(max(0, (float)$o->wallet_paid - (float)$o->kit_amount), 2);
            if ($vendorShare > 0) {
                $seller = Seller::find($o->seller_id);
                if ($seller?->user_id) {
                    $r = app(\App\Services\WalletService::class)->addBalance($seller->user_id, [
                        'amount' => $vendorShare,
                        'payment_method' => 'offline_order',
                        'transaction_reference' => 'offline_order_' . $o->id,
                        'description' => "Offline order {$o->order_no} (paid by BJO wallet)",
                    ]);
                    if (empty($r['success'])) {
                        throw ValidationException::withMessages(['order' => 'Could not credit the vendor wallet. Please try again.']);
                    }
                }
            }
            return $o->fresh();
        });
        ProgramNotifier::offlineOrderCompleted($done);
        return $done;
    }

    /** Cancel a not-yet-collected BJO order (or admin correction): stock, wallet and coins all go back. */
    public function cancel(OfflineOrder $o, string $reason): OfflineOrder
    {
        $done = DB::transaction(function () use ($o, $reason) {
            $o = OfflineOrder::with('items')->whereKey($o->id)->lockForUpdate()->firstOrFail();
            if ($o->status === 'cancelled') return $o;
            if ($o->status === 'completed' && $o->channel === 'worker') {
                throw ValidationException::withMessages(['order' => 'A collected order cannot be cancelled.']);
            }
            if ($o->welcome_kit_purchase_id) {
                throw ValidationException::withMessages(['order' => 'This order includes a Welcome Kit. Cancel the kit from the Welcome Kit admin screen first.']);
            }
            if ($o->status === 'completed') {
                app(CommissionService::class)->reverseOfflineOrder($o, $reason);
                app(VendorCoinService::class)->reverseForOfflineOrder($o);
            }
            foreach ($o->items as $it) {
                if ($it->store_product_variant_id && !$it->is_welcome_kit) {
                    StoreProductVariant::whereKey($it->store_product_variant_id)->increment('stock', $it->quantity);
                }
            }
            $user = User::whereKey($o->user_id)->lockForUpdate()->first();
            if ($o->coins_used > 0) {
                $this->postCoins($user, 'refund', (int)$o->coins_used, $o, "Coins returned — {$o->order_no} cancelled");
            }
            if ($o->coins_credited && $o->coins_earned > 0) {
                $this->postCoins($user, 'reverse', -(int)$o->coins_earned, $o, "Cashback reversed — {$o->order_no} cancelled");
            }
            if ((float)$o->wallet_paid > 0 && $o->network_member_id) {
                $m = NetworkMember::whereKey($o->network_member_id)->lockForUpdate()->first();
                if ($m) {
                    $m->order_wallet_balance = (float)$m->order_wallet_balance + (float)$o->wallet_paid;
                    $m->save();
                    NetworkWalletLedger::create([
                        'network_member_id' => $m->id, 'type' => 'credit', 'wallet' => 'order', 'amount' => $o->wallet_paid,
                        'balance_after' => $m->order_wallet_balance, 'reference_type' => 'offline_order', 'reference_id' => $o->id,
                        'description' => "Refund — offline order {$o->order_no} cancelled",
                    ]);
                }
            }
            $o->update(['status' => 'cancelled', 'cancel_reason' => $reason]);
            return $o->fresh();
        });
        if ($done->status === 'cancelled') {
            ProgramNotifier::offlineOrderCancelled($done);
        }
        return $done;
    }

    // ------------------------------------------------------------------ internals

    protected function coinsToUse(User $user, float $subtotal, array $pay): int
    {
        $want = (int)($pay['coins'] ?? 0);
        if (!empty($pay['use_all_coins'])) $want = PHP_INT_MAX;
        if ($want <= 0 || !app(CoinService::class)->enabled()) return 0;
        $value = app(CoinService::class)->coinValue();
        $maxPct = min(100, max(0, (float)ProgramSettings::get('max_coin_redeem_percent', 100)));
        $maxCoins = (int)floor(($subtotal * $maxPct / 100) / $value);
        $coins = min($want, (int)$user->coin_balance, $maxCoins);
        if (($pay['coins'] ?? 0) > 0 && empty($pay['use_all_coins']) && $want > (int)$user->coin_balance) {
            throw ValidationException::withMessages(['coins' => 'The card has only ' . (int)$user->coin_balance . ' coins.']);
        }
        return max(0, $coins);
    }

    protected function debitBjoWallet(?NetworkMember $bjo, float $amount, OfflineOrder $order): void
    {
        $m = NetworkMember::whereKey($bjo?->id)->lockForUpdate()->first();
        if (!$m || (float)$m->order_wallet_balance + 0.00001 < $amount) {
            throw ValidationException::withMessages(['wallet' => 'Insufficient order wallet balance. Needed ₹' . number_format($amount, 2)
                . ', available ₹' . number_format((float)($m->order_wallet_balance ?? 0), 2) . '. Recharge your wallet (Razorpay / earnings) and try again.']);
        }
        $m->order_wallet_balance = round((float)$m->order_wallet_balance - $amount, 2);
        $m->save();
        NetworkWalletLedger::create([
            'network_member_id' => $m->id, 'type' => 'debit', 'wallet' => 'order', 'amount' => $amount, 'balance_after' => $m->order_wallet_balance,
            'reference_type' => 'offline_order', 'reference_id' => $order->id,
            'description' => "Offline order {$order->order_no} for card holder",
        ]);
    }

    /** Everything that happens when goods change hands: kit, cashback coins, commissions, vendor coins. */
    protected function completeOrder(OfflineOrder $o): void
    {
        $this->finalizeKit($o);          // first: the customer becomes a kit holder, so repurchase rules apply to this order too
        $this->creditCoinsEarned($o);
        try {
            app(CommissionService::class)->onOfflineOrderCompleted($o->fresh());
            app(VendorCoinService::class)->creditForOfflineOrder($o);
        } catch (\Throwable $e) {
            Log::error('Offline order commission hooks failed: ' . $e->getMessage(), ['offline_order_id' => $o->id]);
        }
    }

    /** Creates the Welcome Kit purchase for a kit line: card holder gets the kit, worker/vendor/BSO kit commissions are paid. */
    protected function finalizeKit(OfflineOrder $o): void
    {
        $line = OfflineOrderItem::where('offline_order_id', $o->id)->where('is_welcome_kit', true)->first();
        if (!$line || $o->welcome_kit_purchase_id) return;

        $user = User::whereKey($o->user_id)->lockForUpdate()->first();
        if (app(WelcomeKitService::class)->hasActivePurchase($user)) return;   // bought elsewhere meanwhile

        $kit = WelcomeKit::with('items')->find($line->welcome_kit_id);
        $worker = $o->network_member_id
            ? NetworkMember::where('id', $o->network_member_id)->where('status', 'active')->first()
            : ($user->referred_by_worker_id ? NetworkMember::where('id', $user->referred_by_worker_id)->where('status', 'active')->first() : null);

        // a customer with no network trail yet is linked to the vendor who sold the kit (same as a vendor QR referral)
        if (!$worker && !$user->referred_by_worker_id && !$user->referred_by_seller_id) {
            $user->referred_by_seller_id = $o->seller_id;
        }

        $purchase = WelcomeKitPurchase::create([
            'user_id' => $user->id,
            'welcome_kit_id' => $kit?->id,
            'amount' => $line->subtotal,
            'payment_method' => $o->channel === 'worker' ? 'bjo_wallet' : 'offline_' . ($o->payment_mode === 'mixed' || $o->payment_mode === 'coins' ? 'cash' : $o->payment_mode),
            'worker_member_id' => $worker?->id,
            'referral_code_used' => $worker?->referral_code,
            'status' => 'delivered',                                  // handed over at the vendor
            'admin_note' => "Sold offline — order {$o->order_no}",
            'items_snapshot' => $kit ? $kit->items->map(fn($i) => ['product_id' => $i->product_id, 'name' => $i->item_name, 'quantity' => $i->quantity])->values()->all() : [],
        ]);
        $user->forceFill([
            'welcome_kit_purchased_at' => now(),
            'referred_by_worker_id' => $user->referred_by_worker_id ?: $worker?->id,
        ])->save();

        app(WelcomeKitService::class)->issueCard($user, $purchase);
        app(CommissionService::class)->onWelcomeKitPurchased($purchase->fresh(['user', 'worker']));
        $o->forceFill(['welcome_kit_purchase_id' => $purchase->id])->saveQuietly();

        // kit money collected at the counter belongs to SmartBazars: it is netted off the vendor's settlement
        if ($o->channel === 'counter' && (float)$o->kit_amount > 0) {
            $seller = Seller::find($o->seller_id);
            if ($seller && !$seller->is_house_store) {
                app(\App\Services\SellerStatementService::class)->addEntry([
                    'seller_id' => $seller->id,
                    'entry_type' => \App\Enums\Seller\SellerSettlementTypeEnum::DEBIT(),
                    'amount' => (float)$o->kit_amount,
                    'currency_code' => 'INR',
                    'reference_type' => 'offline_welcome_kit',
                    'reference_id' => (string)$o->id,
                    'description' => "Welcome Kit amount collected at counter ({$o->order_no}) — payable to SmartBazars",
                ]);
            }
        }
    }

    protected function creditCoinsEarned(OfflineOrder $o): void
    {
        if ($o->coins_credited || $o->coins_earned <= 0 || !app(CoinService::class)->enabled()) {
            $o->update(['coins_credited' => true]);
            return;
        }
        $user = User::whereKey($o->user_id)->lockForUpdate()->first();
        $this->postCoins($user, 'earn', (int)$o->coins_earned, $o, "Cashback — offline order {$o->order_no}");
        $o->update(['coins_credited' => true]);
    }

    protected function postCoins(User $user, string $type, int $coins, OfflineOrder $o, string $desc): void
    {
        $new = max(0, (int)$user->coin_balance + $coins);
        CoinTransaction::create([
            'user_id' => $user->id, 'type' => $type, 'coins' => $coins, 'balance_after' => $new,
            'offline_order_id' => $o->id, 'description' => $desc,
        ]);
        $user->forceFill(['coin_balance' => $new])->save();
    }

    protected function nextOrderNo(): string
    {
        $prefix = 'OFL' . now()->format('ymd');
        $n = OfflineOrder::where('order_no', 'like', $prefix . '%')->count() + 1;
        do {
            $no = $prefix . str_pad((string)$n++, 5, '0', STR_PAD_LEFT);
        } while (OfflineOrder::where('order_no', $no)->exists());
        return $no;
    }
}
