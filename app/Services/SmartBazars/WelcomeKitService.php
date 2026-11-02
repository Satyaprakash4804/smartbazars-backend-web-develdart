<?php

namespace App\Services\SmartBazars;

use App\Enums\Wallet\WalletTransactionStatusEnum;
use App\Enums\Wallet\WalletTransactionTypeEnum;
use App\Models\Address;
use App\Models\NetworkMember;
use App\Models\User;
use App\Models\UserCard;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WelcomeKit;
use App\Models\WelcomeKitPurchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Welcome kit purchase (paid from the customer's wallet) → User Card issue → commission distribution.
 * The customer tops up the wallet with the existing wallet-recharge flow if the balance is short.
 */
class WelcomeKitService
{
    public function __construct(protected CommissionService $commissions) {}

    public function hasActivePurchase(User $user): bool
    {
        return WelcomeKitPurchase::where('user_id', $user->id)->where('status', '!=', 'cancelled')->exists();
    }

    public function resolveWorker(?string $code, User $user): ?NetworkMember
    {
        if ($code) {
            // v1.4: worker code (SBW…) or vendor QR code (SBV…)
            $r = app(ReferralService::class)->resolve($code);
            if (!$r) {
                throw ValidationException::withMessages(['referral_code' => 'Invalid referral code.']);
            }
            if ($r['type'] === 'worker') {
                return $r['worker'];
            }
            app(ReferralService::class)->linkCustomer($user, $code); // vendor code: link to vendor trail
            $user->refresh();
            return $user->referred_by_worker_id
                ? NetworkMember::where('id', $user->referred_by_worker_id)->where('status', 'active')->first()
                : null;
        }
        return $user->referred_by_worker_id
            ? NetworkMember::where('id', $user->referred_by_worker_id)->where('status', 'active')->first()
            : null;
    }

    public function purchase(User $user, array $data): WelcomeKitPurchase
    {
        if ($this->hasActivePurchase($user)) {
            throw ValidationException::withMessages(['welcome_kit' => 'You have already purchased the welcome kit.']);
        }
        $kit = WelcomeKit::active();
        if (!$kit) {
            throw ValidationException::withMessages(['welcome_kit' => 'Welcome kit is not available right now.']);
        }
        $worker = $this->resolveWorker($data['referral_code'] ?? null, $user);
        if (!$worker && !$user->referred_by_seller_id && ProgramSettings::get('kit_requires_referral_code')) {
            throw ValidationException::withMessages(['referral_code' => 'Please enter your SmartBazars worker referral code.']);
        }
        $address = Address::where('user_id', $user->id)->where('id', $data['address_id'] ?? 0)->first();
        if (!$address) {
            throw ValidationException::withMessages(['address_id' => 'Please select a delivery address.']);
        }

        $purchase = DB::transaction(function () use ($user, $kit, $worker, $address, $data) {
            $price = (float)$kit->price;
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
            if (!$wallet || (float)$wallet->balance < $price) {
                throw ValidationException::withMessages([
                    'wallet' => 'Insufficient wallet balance. Please recharge your wallet with at least ₹'
                        . number_format($price - (float)($wallet->balance ?? 0), 2) . '.',
                ]);
            }
            $wallet->balance = (float)$wallet->balance - $price;
            $wallet->save();

            $tx = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'transaction_type' => WalletTransactionTypeEnum::PAYMENT(),
                'payment_method' => 'wallet',
                'amount' => $price,
                'currency_code' => $wallet->currency_code,
                'status' => WalletTransactionStatusEnum::COMPLETED(),
                'transaction_reference' => 'welcome_kit_' . $user->id . '_' . now()->format('YmdHis') . '_' . random_int(100, 999),
                'description' => 'SmartBazars Welcome Kit purchase',
            ]);

            $purchase = WelcomeKitPurchase::create([
                'user_id' => $user->id,
                'welcome_kit_id' => $kit->id,
                'amount' => $price,
                'payment_method' => 'wallet',
                'wallet_transaction_id' => $tx->id,
                'worker_member_id' => $worker?->id,
                'referral_code_used' => $worker?->referral_code,
                'status' => 'paid',
                'address_id' => $address->id,
                'address_snapshot' => $address->only(['address_line1', 'address_line2', 'landmark', 'city', 'state', 'zipcode', 'mobile', 'country', 'latitude', 'longitude']),
                'items_snapshot' => $kit->items->map(fn($i) => [
                    'product_id' => $i->product_id,
                    'name' => $i->item_name,
                    'quantity' => $i->quantity,
                ])->values()->all(),
            ]);

            $user->forceFill([
                'welcome_kit_purchased_at' => now(),
                'referred_by_worker_id' => $user->referred_by_worker_id ?: $worker?->id,
            ])->save();

            $this->issueCard($user, $purchase);
            $this->commissions->onWelcomeKitPurchased($purchase);

            return $purchase;
        });

        return $purchase->fresh(['card', 'kit.items']);
    }

    public function issueCard(User $user, ?WelcomeKitPurchase $purchase = null): UserCard
    {
        $existing = UserCard::where('user_id', $user->id)->first();
        $years = max(1, (int)ProgramSettings::get('card_validity_years', 5));
        if ($existing) {
            $existing->update([
                'status' => 'active',
                'welcome_kit_purchase_id' => $purchase?->id ?? $existing->welcome_kit_purchase_id,
                'valid_upto' => now()->addYears($years - 1)->endOfYear()->toDateString(),
            ]);
            return $existing;
        }
        $prefix = strtoupper(ProgramSettings::get('card_prefix', 'SB'));
        $year = now()->format('Y');
        $seq = UserCard::where('card_number', 'like', $prefix . $year . '%')->count() + 1;
        do {
            $number = $prefix . $year . str_pad((string)$seq, 6, '0', STR_PAD_LEFT);
            $seq++;
        } while (UserCard::where('card_number', $number)->exists());

        return UserCard::create([
            'user_id' => $user->id,
            'welcome_kit_purchase_id' => $purchase?->id,
            'card_number' => $number,
            'issued_at' => now()->toDateString(),
            // like the sample card: valid until 31-12 of the final year
            'valid_upto' => now()->addYears($years - 1)->endOfYear()->toDateString(),
            'status' => 'active',
        ]);
    }

    /** Admin cancels a kit: refund wallet, reverse commissions, block card. */
    public function cancel(WelcomeKitPurchase $purchase, string $reason): void
    {
        if ($purchase->status === 'cancelled') return;
        if ($purchase->status === 'delivered') {
            throw ValidationException::withMessages(['status' => 'A delivered kit cannot be cancelled.']);
        }
        DB::transaction(function () use ($purchase, $reason) {
            app(\App\Services\WalletService::class)->addBalance($purchase->user_id, [
                'amount' => (float)$purchase->amount,
                'payment_method' => 'refund',
                'transaction_reference' => 'welcome_kit_refund_' . $purchase->id,
                'description' => 'Refund: Welcome Kit #' . $purchase->id . ' cancelled',
            ]);
            $this->commissions->reverseWelcomeKit($purchase, $reason);
            UserCard::where('welcome_kit_purchase_id', $purchase->id)->update(['status' => 'blocked']);
            $purchase->update(['status' => 'cancelled', 'admin_note' => $reason]);
            User::whereKey($purchase->user_id)->update(['welcome_kit_purchased_at' => null]);
        });
    }
}
