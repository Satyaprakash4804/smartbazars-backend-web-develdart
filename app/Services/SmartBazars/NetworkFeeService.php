<?php

namespace App\Services\SmartBazars;

use App\Http\Controllers\Payments\RazorpayController;
use App\Models\NetworkFeePayment;
use App\Models\NetworkRegistrationRequest;
use App\Models\Seller;
use App\Notifications\SmartBazars\NetworkFeeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Joining fees for BSOs / BJOs / Vendors, paid through Razorpay.
 *
 * IMPORTANT: a joining fee is company income. It is deliberately NOT routed through CommissionService —
 * no agent, upline, vendor or worker earns anything from it.
 */
class NetworkFeeService
{
    /** Master switch in admin settings. OFF => nobody is asked to pay. */
    public function enabled(): bool
    {
        return (bool)ProgramSettings::get('joining_fee_enabled', true);
    }

    public function feeFor(string $type): float
    {
        if (!$this->enabled()) return 0.0;
        $key = ['agent' => 'joining_fee_agent', 'worker' => 'joining_fee_worker', 'vendor' => 'joining_fee_vendor'][$type] ?? null;
        return $key ? max(0.0, round((float)ProgramSettings::get($key, 0), 2)) : 0.0;
    }

    public function required(string $type): bool
    {
        return $this->feeFor($type) > 0;
    }

    /**
     * Put a request on hold for payment (status pending_payment) and create the fee row.
     * Returns null when no payment is needed (or an earlier fee is re-used) — caller then continues normally.
     */
    public function startForRequest(NetworkRegistrationRequest $req, string $purpose, string $returnUrl): ?NetworkFeePayment
    {
        if (!$this->required($req->type)) {
            $req->update(['payment_status' => 'not_required', 'fee_amount' => null]);
            return null;
        }

        if ($this->reuseEarlierFee($req)) {
            return null;
        }

        $amount = $this->feeFor($req->type);
        $req->update(['status' => 'pending_payment', 'payment_status' => 'pending', 'fee_amount' => $amount]);

        return NetworkFeePayment::create([
            'purpose' => $purpose,
            'registration_request_id' => $req->id,
            'payer_name' => $req->name,
            'payer_email' => $req->email,
            'amount' => $amount,
            'return_url' => $returnUrl,
        ]);
    }

    public function startForSeller(Seller $seller): ?NetworkFeePayment
    {
        $amount = $this->feeFor('vendor');
        if ($amount <= 0) return null;

        return NetworkFeePayment::firstOrCreate(
            ['seller_id' => $seller->id, 'purpose' => 'vendor_joining', 'status' => 'pending'],
            [
                'payer_name' => $seller->user->name ?? null,
                'payer_email' => $seller->user->email ?? null,
                'amount' => $amount,
                'return_url' => route('seller.login'),
            ]
        );
    }

    /** Pending vendor joining fee that blocks approval of a self-registered seller (null = nothing owed). */
    public function pendingVendorFee(Seller $seller): ?NetworkFeePayment
    {
        return NetworkFeePayment::where('seller_id', $seller->id)->where('purpose', 'vendor_joining')->where('status', 'pending')->first();
    }

    /** Create (or reuse) the Razorpay order for this fee. Returns the order id. */
    public function ensureOrder(NetworkFeePayment $p): string
    {
        if ($p->razorpay_order_id) return $p->razorpay_order_id;

        $res = app(RazorpayController::class)->createNetworkFeeOrder([
            'amount' => (float)$p->amount,
            'fee_payment_id' => $p->id,
            'receipt' => 'netfee-' . $p->id,
        ]);
        if (!($res['success'] ?? false)) {
            throw new \RuntimeException($res['message'] ?? 'Unable to start the payment.');
        }
        $p->update(['razorpay_order_id' => $res['data']['id']]);
        return $p->razorpay_order_id;
    }

    public function verifyAndMarkPaid(NetworkFeePayment $p, string $orderId, string $paymentId, string $signature): bool
    {
        if ($p->razorpay_order_id !== $orderId) return false;
        $check = app(RazorpayController::class)->verifyPayment([
            'razorpay_order_id' => $orderId, 'transaction_id' => $paymentId, 'razorpay_signature' => $signature,
        ]);
        if (!($check['success'] ?? false)) return false;
        return $this->markPaid($p, $paymentId);
    }

    /** Idempotent — called by the checkout callback and by the Razorpay webhook. */
    public function markPaid(NetworkFeePayment $p, string $razorpayPaymentId): bool
    {
        return DB::transaction(function () use ($p, $razorpayPaymentId) {
            $fresh = NetworkFeePayment::whereKey($p->id)->lockForUpdate()->first();
            if (!$fresh || $fresh->status !== 'pending') {
                return $fresh?->isSettled() ?? false;
            }
            $fresh->update(['status' => 'paid', 'razorpay_payment_id' => $razorpayPaymentId, 'paid_at' => now()]);

            if ($fresh->registration_request_id) {
                $req = NetworkRegistrationRequest::find($fresh->registration_request_id);
                if ($req) {
                    $req->update(['payment_status' => 'paid']);
                    if ($req->status === 'pending_payment') {
                        app(NetworkService::class)->afterPayment($req);
                    }
                }
            }
            return true;
        });
    }

    public function waiveRequest(NetworkRegistrationRequest $req): void
    {
        DB::transaction(function () use ($req) {
            NetworkFeePayment::where('registration_request_id', $req->id)->where('status', 'pending')->update(['status' => 'waived']);
            $req->update(['payment_status' => 'waived']);
            if ($req->status === 'pending_payment') {
                app(NetworkService::class)->afterPayment($req->fresh());
            }
        });
    }

    public function waiveSeller(Seller $seller): void
    {
        NetworkFeePayment::where('seller_id', $seller->id)->where('purpose', 'vendor_joining')->where('status', 'pending')->update(['status' => 'waived']);
    }

    public function sendLink(NetworkFeePayment $p, string $role): void
    {
        if (!$p->payer_email) return;
        try {
            Notification::route('mail', $p->payer_email)->notify(
                new NetworkFeeNotification('pay_link', $p->payer_name ?: 'there', $role, $p->payUrl(), (float)$p->amount)
            );
        } catch (\Throwable $e) {
            Log::error('SmartBazars fee link mail failed: ' . $e->getMessage());
        }
    }

    /** A fee already paid on a rejected application is carried over to the re-application (admin setting). */
    protected function reuseEarlierFee(NetworkRegistrationRequest $req): bool
    {
        if (!$req->reapplied_from_id || !ProgramSettings::get('joining_fee_reuse_on_reapply', true)) return false;
        $old = NetworkRegistrationRequest::find($req->reapplied_from_id);
        if (!$old || $old->payment_status !== 'paid' || !empty($old->meta['fee_credit_used']) || strtolower($old->email) !== strtolower($req->email)) {
            return false;
        }
        $old->update(['meta' => array_merge($old->meta ?? [], ['fee_credit_used' => true])]);
        $req->update(['payment_status' => 'paid', 'fee_amount' => $old->fee_amount]);
        NetworkFeePayment::create([
            'purpose' => 'application', 'registration_request_id' => $req->id, 'payer_name' => $req->name,
            'payer_email' => $req->email, 'amount' => $old->fee_amount, 'status' => 'paid', 'paid_at' => now(),
            'meta' => ['reused_from_request_id' => $old->id],
        ]);
        return true;
    }
}
