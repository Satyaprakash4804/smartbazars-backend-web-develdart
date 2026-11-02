<?php

namespace App\Services\SmartBazars;

use App\Http\Controllers\Payments\RazorpayController;
use App\Models\NetworkMember;
use App\Models\NetworkWalletLedger;
use App\Models\NetworkWalletRecharge;
use App\Models\CoinTransaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BJO / BSO wallets.
 *   earnings wallet (network_members.wallet_balance)      : commissions — withdrawable
 *   order wallet    (network_members.order_wallet_balance): pays customer orders at the POS
 * The order wallet is filled by (1) Razorpay online recharge, (2) a recharge request approved by admin,
 * (3) moving money from the earnings wallet. Every movement is written to network_wallet_ledger.
 */
class NetworkWalletService
{
    public const DISK = 'local';

    protected function min(): float
    {
        return (float)ProgramSettings::get('wallet_min_recharge', 100);
    }

    // ------------------------------------------------------------------ online recharge (Razorpay)

    /** Step 1: create a pending recharge + Razorpay order. Returns data for the Checkout popup. */
    public function startRazorpay(NetworkMember $member, float $amount): array
    {
        if ($amount < $this->min()) {
            throw ValidationException::withMessages(['amount' => 'Minimum recharge is ₹' . number_format($this->min(), 0) . '.']);
        }
        if ($amount > 200000) {
            throw ValidationException::withMessages(['amount' => 'Maximum online recharge is ₹2,00,000 at a time.']);
        }

        $r = NetworkWalletRecharge::create([
            'network_member_id' => $member->id, 'amount' => round($amount, 2), 'method' => 'razorpay', 'gateway' => 'razorpay',
            'reference' => 'RZP-' . strtoupper(bin2hex(random_bytes(5))), 'status' => 'pending',
        ]);

        $gw = app(RazorpayController::class);
        $res = $gw->createNetworkWalletOrder(['amount' => $amount, 'recharge_id' => $r->id, 'receipt' => 'bjo-wallet-' . $r->id]);
        if (!($res['success'] ?? false)) {
            $r->delete();
            throw ValidationException::withMessages(['amount' => $res['message'] ?? 'Unable to start the payment.']);
        }
        $r->update(['gateway_order_id' => $res['data']['id'], 'reference' => $res['data']['id']]);

        $user = $member->user;
        return [
            'recharge_id' => $r->id,
            'key' => $gw->keyId(),
            'order_id' => $res['data']['id'],
            'amount_paise' => (int)round($amount * 100),
            'name' => $user?->name, 'email' => $user?->email, 'contact' => $user?->mobile,
        ];
    }

    /** Step 2: browser callback. Verifies the Razorpay signature, then credits the order wallet (idempotent). */
    public function verifyRazorpay(NetworkMember $member, string $orderId, string $paymentId, string $signature): NetworkWalletRecharge
    {
        $r = NetworkWalletRecharge::where('network_member_id', $member->id)->where('gateway_order_id', $orderId)->first();
        if (!$r) {
            throw ValidationException::withMessages(['payment' => 'Payment not found.']);
        }
        $check = app(RazorpayController::class)->verifyPayment([
            'razorpay_order_id' => $orderId, 'transaction_id' => $paymentId, 'razorpay_signature' => $signature,
        ]);
        if (!($check['success'] ?? false)) {
            throw ValidationException::withMessages(['payment' => 'We could not verify the payment. If money was deducted it will be added automatically within a few minutes.']);
        }
        $this->markPaidByGateway($r, $paymentId);
        return $r->fresh();
    }

    /** Idempotent: called by the browser callback AND by the Razorpay webhook. */
    public function markPaidByGateway(NetworkWalletRecharge $r, string $paymentId): bool
    {
        $credited = false;
        DB::transaction(function () use ($r, $paymentId, &$credited) {
            $fresh = NetworkWalletRecharge::whereKey($r->id)->lockForUpdate()->first();
            if (!$fresh || $fresh->status !== 'pending') {
                return;
            }
            $m = NetworkMember::whereKey($fresh->network_member_id)->lockForUpdate()->firstOrFail();
            $m->order_wallet_balance = round((float)$m->order_wallet_balance + (float)$fresh->amount, 2);
            $m->save();
            $this->ledger($m, 'credit', 'order', (float)$fresh->amount, 'recharge', $fresh->id, 'Wallet recharge via Razorpay (' . $paymentId . ')');
            $fresh->update(['status' => 'approved', 'gateway_payment_id' => $paymentId, 'admin_note' => 'Paid online — auto approved', 'reviewed_at' => now()]);
            $credited = true;
        });

        if ($credited) {
            $r->refresh()->loadMissing('member.user');
            ProgramNotifier::member($r->member, 'Wallet recharged ₹' . number_format((float)$r->amount, 2),
                'Your Razorpay payment was successful. The amount is in your order wallet.', 'wallet_transaction', ['recharge_id' => $r->id]);
        }
        return $credited || $r->fresh()->status === 'approved';
    }

    // ------------------------------------------------------------------ earnings -> order wallet

    public function transferEarnings(NetworkMember $member, float $amount): NetworkMember
    {
        $amount = round($amount, 2);
        if ($amount < 1) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount of at least ₹1.']);
        }
        DB::transaction(function () use ($member, $amount) {
            $m = NetworkMember::whereKey($member->id)->lockForUpdate()->firstOrFail();
            if ((float)$m->wallet_balance + 0.00001 < $amount) {
                throw ValidationException::withMessages(['amount' => 'You only have ₹' . number_format((float)$m->wallet_balance, 2) . ' in your earnings wallet.']);
            }
            $m->wallet_balance = round((float)$m->wallet_balance - $amount, 2);
            $m->order_wallet_balance = round((float)$m->order_wallet_balance + $amount, 2);
            $m->save();
            $this->ledger($m, 'debit', 'earnings', $amount, 'transfer', $m->id, 'Moved to order wallet', (float)$m->wallet_balance);
            $this->ledger($m, 'credit', 'order', $amount, 'transfer', $m->id, 'Moved from earnings wallet', (float)$m->order_wallet_balance);
        });
        $member->refresh();
        ProgramNotifier::member($member, '₹' . number_format($amount, 2) . ' moved to order wallet', 'Your earnings were added to your order wallet.', 'wallet_transaction', ['amount' => $amount]);
        return $member;
    }

    // ------------------------------------------------------------------ cashback coins -> order wallet

    /** Coins on the BJO's own SmartBazars account and what they are worth right now. */
    public function coinSummary(NetworkMember $member): array
    {
        $coinSvc = app(CoinService::class);
        $coins = (int)(User::whereKey($member->user_id)->value('coin_balance') ?? 0);
        $rate = $coinSvc->coinValue();
        return ['coins' => $coins, 'rate' => $rate, 'value' => round($coins * $rate, 2), 'enabled' => $coinSvc->enabled()];
    }

    /**
     * Moves cashback coins from the BJO's account into the order wallet at the admin's coin value
     * (Program settings > coin_value, default 1 coin = ₹1). Both sides are written in one DB transaction:
     * a "redeem" line in coin history and a credit line in the wallet ledger.
     */
    public function transferCoins(NetworkMember $member, int $coins): array
    {
        $coinSvc = app(CoinService::class);
        if (!$coinSvc->enabled()) {
            throw ValidationException::withMessages(['coins' => 'Cashback coins are switched off right now.']);
        }
        if ($coins < 1) {
            throw ValidationException::withMessages(['coins' => 'Enter at least 1 coin.']);
        }
        $value = round($coins * $coinSvc->coinValue(), 2);
        if ($value < 0.01) {
            throw ValidationException::withMessages(['coins' => 'Too few coins to convert.']);
        }

        DB::transaction(function () use ($member, $coins, $value) {
            $u = User::whereKey($member->user_id)->lockForUpdate()->firstOrFail();
            if ((int)$u->coin_balance < $coins) {
                throw ValidationException::withMessages(['coins' => 'You only have ' . (int)$u->coin_balance . ' coins.']);
            }
            $u->coin_balance = (int)$u->coin_balance - $coins;
            $u->save();
            CoinTransaction::create([
                'user_id' => $u->id, 'type' => 'redeem', 'coins' => -$coins, 'balance_after' => (int)$u->coin_balance,
                'description' => 'Moved to BJO order wallet (₹' . number_format($value, 2) . ')',
            ]);

            $m = NetworkMember::whereKey($member->id)->lockForUpdate()->firstOrFail();
            $m->order_wallet_balance = round((float)$m->order_wallet_balance + $value, 2);
            $m->save();
            $this->ledger($m, 'credit', 'order', $value, 'coins', (int)$u->id, $coins . ' cashback coins moved to order wallet');
        });

        $member->refresh();
        ProgramNotifier::member($member, '₹' . number_format($value, 2) . ' added from coins',
            $coins . ' cashback coins were moved to your order wallet.', 'wallet_transaction', ['coins' => $coins, 'amount' => $value]);

        return ['coins' => $coins, 'value' => $value, 'order_balance' => (float)$member->order_wallet_balance];
    }

    // ------------------------------------------------------------------ manual recharge request (bank / cash)

    public function request(NetworkMember $member, array $data, ?UploadedFile $proof): NetworkWalletRecharge
    {
        if ((float)$data['amount'] < $this->min()) {
            throw ValidationException::withMessages(['amount' => 'Minimum recharge is ₹' . number_format($this->min(), 0) . '.']);
        }
        $ref = strtoupper(trim($data['reference']));
        if (NetworkWalletRecharge::where('network_member_id', $member->id)->where('reference', $ref)->exists()) {
            throw ValidationException::withMessages(['reference' => 'This reference number was already submitted.']);
        }
        $path = $proof ? $proof->storeAs('wallet-proofs/' . $member->id, now()->format('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . ($proof->guessExtension() ?: 'jpg'), self::DISK) : null;

        $r = NetworkWalletRecharge::create([
            'network_member_id' => $member->id, 'amount' => round((float)$data['amount'], 2), 'method' => $data['method'],
            'reference' => $ref, 'proof_file' => $path, 'note' => $data['note'] ?? null, 'status' => 'pending',
        ]);
        ProgramNotifier::admins('BJO wallet recharge request',
            ($member->user?->name ?? 'BJO') . ' requested ₹' . number_format((float)$r->amount, 2) . ' (ref ' . $r->reference . ').', 'wallet_transaction', ['recharge_id' => $r->id]);
        return $r;
    }

    public function approve(NetworkWalletRecharge $r, int $adminId, ?string $note = null): NetworkWalletRecharge
    {
        DB::transaction(function () use (&$r, $adminId, $note) {
            $r = NetworkWalletRecharge::whereKey($r->id)->lockForUpdate()->firstOrFail();
            if ($r->status !== 'pending' || $r->gateway) {
                throw ValidationException::withMessages(['recharge' => 'This request cannot be approved (already processed or paid online).']);
            }
            $m = NetworkMember::whereKey($r->network_member_id)->lockForUpdate()->firstOrFail();
            $m->order_wallet_balance = round((float)$m->order_wallet_balance + (float)$r->amount, 2);
            $m->save();
            $this->ledger($m, 'credit', 'order', (float)$r->amount, 'recharge', $r->id, 'Wallet recharge approved (ref ' . $r->reference . ')');
            $r->update(['status' => 'approved', 'admin_note' => $note, 'reviewed_by' => $adminId, 'reviewed_at' => now()]);
        });
        ProgramNotifier::member($r->member, 'Wallet recharged ₹' . number_format((float)$r->amount, 2),
            'Your recharge (ref ' . $r->reference . ') was approved. You can place customer orders now.', 'wallet_transaction', ['recharge_id' => $r->id]);
        return $r;
    }

    public function reject(NetworkWalletRecharge $r, int $adminId, string $note): NetworkWalletRecharge
    {
        if ($r->status !== 'pending' || $r->gateway) {
            throw ValidationException::withMessages(['recharge' => 'This request cannot be rejected (already processed or paid online).']);
        }
        $r->update(['status' => 'rejected', 'admin_note' => $note, 'reviewed_by' => $adminId, 'reviewed_at' => now()]);
        ProgramNotifier::member($r->member, 'Wallet recharge rejected', 'Reference ' . $r->reference . ': ' . $note, 'wallet_transaction', ['recharge_id' => $r->id]);
        return $r;
    }

    // ------------------------------------------------------------------ internals

    protected function ledger(NetworkMember $m, string $type, string $wallet, float $amount, string $ref, int $refId, string $desc, ?float $balanceAfter = null): void
    {
        NetworkWalletLedger::create([
            'network_member_id' => $m->id, 'type' => $type, 'wallet' => $wallet, 'amount' => $amount,
            'balance_after' => $balanceAfter ?? ($wallet === 'order' ? (float)$m->order_wallet_balance : (float)$m->wallet_balance),
            'reference_type' => $ref, 'reference_id' => $refId, 'description' => $desc,
        ]);
    }
}
