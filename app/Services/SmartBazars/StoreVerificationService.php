<?php

namespace App\Services\SmartBazars;

use App\Enums\GuardNameEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Models\Store;
use App\Models\User;
use App\Notifications\SmartBazars\StoreReviewNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Store verification: a vendor's new store is created "pending review" (hidden from customers).
 * Admin approves it (store becomes visible) or rejects it with a reason; the vendor can fix and resubmit.
 */
class StoreVerificationService
{
    public function __construct(protected KycService $kyc) {}

    /** What admin should check. `required` items block approval until fixed. */
    public function checklist(Store $s): array
    {
        $seller = $s->seller;
        $kycOk = !$seller || $seller->is_house_store || !$this->kyc->sellerMissing($seller);
        $has = fn($c) => $s->hasMedia($c);

        return [
            ['label' => 'Vendor KYC completed', 'ok' => $kycOk, 'required' => true, 'hint' => $kycOk ? null : 'The vendor must complete KYC first (asked at their next login).'],
            ['label' => 'Inside a delivery zone', 'ok' => $s->zones()->exists(), 'required' => true, 'hint' => 'Store location must fall in a delivery zone.'],
            ['label' => 'Store logo', 'ok' => $has(SpatieMediaCollectionName::STORE_LOGO()), 'required' => true, 'hint' => null],
            ['label' => 'Address proof', 'ok' => $has(SpatieMediaCollectionName::ADDRESS_PROOF()), 'required' => true, 'hint' => null],
            ['label' => 'Voided cheque', 'ok' => $has(SpatieMediaCollectionName::VOIDED_CHECK()), 'required' => true, 'hint' => null],
            ['label' => 'Contact & address filled', 'ok' => filled($s->contact_number) && filled($s->address) && filled($s->city), 'required' => true, 'hint' => null],
            ['label' => 'Tax & bank details filled', 'ok' => filled($s->tax_number) && filled($s->account_number), 'required' => false, 'hint' => null],
        ];
    }

    public function blockers(Store $s): array
    {
        return collect($this->checklist($s))->where('required', true)->where('ok', false)->pluck('label')->all();
    }

    public function approve(Store $s, int $adminId): void
    {
        if ($blockers = $this->blockers($s)) {
            throw ValidationException::withMessages(['store' => 'Cannot approve yet — missing: ' . implode(', ', $blockers) . '.']);
        }
        // the store observer sends the vendor the "verification status updated" notification
        $s->update([
            'verification_status' => 'approved', 'visibility_status' => 'visible',
            'rejection_reason' => null, 'verified_at' => now(), 'verified_by' => $adminId,
        ]);
    }

    public function reject(Store $s, int $adminId, string $reason): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            throw ValidationException::withMessages(['reason' => 'Please give a reason — the vendor sees it.']);
        }
        $s->update([
            'verification_status' => 'not_approved', 'visibility_status' => 'draft',
            'rejection_reason' => $reason, 'verified_at' => null, 'verified_by' => $adminId,
        ]);
        $this->notify($s->seller?->user, new StoreReviewNotification('rejected', $s, $reason));
    }

    /** Vendor edited a rejected store: it goes back to "pending review" and admins are told. */
    public function resubmitted(Store $s): void
    {
        if (!filled($s->rejection_reason)) return;
        $s->update(['rejection_reason' => null]);
        foreach (User::where('access_panel', GuardNameEnum::ADMIN())->get() as $admin) {
            $this->notify($admin, new StoreReviewNotification('resubmitted', $s));
        }
    }

    protected function notify(?User $user, StoreReviewNotification $n): void
    {
        if (!$user) return;
        try {
            $user->notify($n);
        } catch (\Throwable $e) {
            Log::error('Store review notification failed: ' . $e->getMessage(), ['user_id' => $user->id]);
        }
    }
}
