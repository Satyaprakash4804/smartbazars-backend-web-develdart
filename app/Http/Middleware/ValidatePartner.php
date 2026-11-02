<?php

namespace App\Http\Middleware;

use App\Models\NetworkMember;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** SmartBazars partner panel (Agents + Vendor workers). */
class ValidatePartner
{
    public function handle(Request $request, Closure $next, ?string $type = null): Response
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('partner.login');
        }
        $member = NetworkMember::where('user_id', $user->id)->first();
        if (($user->access_panel->value ?? null) !== 'partner' || !$member) {
            Auth::logout();
            return redirect()->route('partner.login')->with('error', 'You do not have access to the partner panel.');
        }
        if ($member->status !== 'active' || $user->getRawOriginal('status') === 'inactive') {
            Auth::logout();
            return redirect()->route('partner.login')->with('error', 'Your account is suspended. Please contact SmartBazars support.');
        }
        if ($type && $member->type !== $type) {
            abort(403, 'This section is only for ' . $type . 's.');
        }
        $request->attributes->set('member', $member);
        view()->share('member', $member);
        return $next($request);
    }
}
