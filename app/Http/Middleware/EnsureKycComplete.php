<?php

namespace App\Http\Middleware;

use App\Models\NetworkMember;
use App\Models\Seller;
use App\Services\SmartBazars\KycService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends Agents, Workers and Vendors to the KYC form on their first login (and until nothing is missing).
 * Usage: kyc.complete:partner  |  kyc.complete:seller
 */
class EnsureKycComplete
{
    public function __construct(protected KycService $kyc) {}

    public function handle(Request $request, Closure $next, string $panel = 'partner'): Response
    {
        $user = Auth::user();
        if (!$user || $request->expectsJson() || $request->ajax()
            || $request->routeIs('partner.kyc*', 'partner.logout', 'partner.photo', 'seller.smartbazars.kyc*', 'seller.logout')) {
            return $next($request);
        }

        if ($panel === 'seller') {
            // admin working inside the SmartBazars own store is never gated
            if (session('impersonating_as_seller', false)) return $next($request);
            $seller = Seller::where('user_id', $user->id)->first();
            if ($seller && !$seller->is_house_store && $this->kyc->sellerMissing($seller)) {
                return redirect()->route('seller.smartbazars.kyc')->with('warning', 'Please complete your KYC details to continue.');
            }
            return $next($request);
        }

        $member = NetworkMember::with('user')->where('user_id', $user->id)->first();
        if ($member && $this->kyc->memberMissing($member)) {
            return redirect()->route('partner.kyc')->with('warning', 'Please complete your KYC details to continue.');
        }
        return $next($request);
    }
}
