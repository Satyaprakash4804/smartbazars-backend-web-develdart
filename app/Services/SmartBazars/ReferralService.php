<?php

namespace App\Services\SmartBazars;

use App\Models\NetworkMember;
use App\Models\Seller;
use App\Models\User;

/**
 * Referral codes and the customer "trail".
 *   SBW…  worker code   -> customer.referred_by_worker_id
 *   SBV…  vendor code   -> customer.referred_by_seller_id   (vendor QR / poster)
 *   SBA…  agent code    -> not used for customers (agents onboard vendors)
 */
class ReferralService
{
    /** @return array{type:string, worker:?NetworkMember, seller:?Seller}|null */
    public function resolve(?string $code): ?array
    {
        $code = strtoupper(trim((string)$code));
        if ($code === '') {
            return null;
        }
        $worker = NetworkMember::where('referral_code', $code)->where('type', NetworkMember::TYPE_WORKER)
            ->where('status', 'active')->first();
        if ($worker) {
            return ['type' => 'worker', 'worker' => $worker, 'seller' => $worker->seller];
        }
        $seller = Seller::where('referral_code', $code)->first();
        if ($seller) {
            return ['type' => 'vendor', 'worker' => null, 'seller' => $seller];
        }
        return null;
    }

    /** Attach a customer to a worker/vendor trail. Never overwrites an existing trail. */
    public function linkCustomer(User $user, ?string $code): bool
    {
        if ($user->referred_by_worker_id || $user->referred_by_seller_id) {
            return false;
        }
        $r = $this->resolve($code);
        if (!$r) {
            return false;
        }
        $user->forceFill($r['type'] === 'worker'
            ? ['referred_by_worker_id' => $r['worker']->id]
            : ['referred_by_seller_id' => $r['seller']->id])->save();
        return true;
    }

    /**
     * The people who earn from this customer's purchases.
     * @return array{worker:?NetworkMember, vendor:?Seller, agent:?NetworkMember, upline:?NetworkMember}
     */
    public function trailFor(User $user): array
    {
        $worker = $user->referred_by_worker_id
            ? NetworkMember::where('id', $user->referred_by_worker_id)->where('status', 'active')->first()
            : null;
        $vendor = $worker?->seller ?? ($user->referred_by_seller_id ? Seller::find($user->referred_by_seller_id) : null);
        $agent = $vendor?->agent_member_id
            ? NetworkMember::where('id', $vendor->agent_member_id)->where('type', 'agent')->where('status', 'active')->first()
            : null;
        $upline = $agent?->parent && $agent->parent->isAgent() && $agent->parent->isActive() ? $agent->parent : null;
        return ['worker' => $worker, 'vendor' => $vendor, 'agent' => $agent, 'upline' => $upline];
    }

    public function publicBaseUrl(): string
    {
        $u = trim((string)ProgramSettings::get('public_site_url', ''));
        return rtrim($u !== '' ? $u : (string)config('app.frontend_url', config('app.url')), '/');
    }

    /** Link printed in QR codes. It opens a SmartBazars landing page with the code. */
    public function referralLink(string $code): string
    {
        return rtrim(config('app.url'), '/') . '/r/' . $code;
    }

    public function verifyLink(string $code): string
    {
        return rtrim(config('app.url'), '/') . '/verify/' . $code;
    }
}
