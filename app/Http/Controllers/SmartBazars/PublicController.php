<?php

namespace App\Http\Controllers\SmartBazars;

use App\Http\Controllers\Controller;
use App\Models\NetworkMember;
use App\Models\Seller;
use App\Models\UserCard;
use App\Services\SmartBazars\DocumentService;
use App\Services\SmartBazars\ProgramSettings;
use App\Services\SmartBazars\ReferralService;

/** Public pages opened from QR codes: /r/{code} (join with referral) and /verify/{code}. */
class PublicController extends Controller
{
    public function referral(string $code, ReferralService $referrals)
    {
        $r = $referrals->resolve($code);
        abort_unless($r, 404);
        $siteUrl = $referrals->publicBaseUrl();
        return view('smartbazars.public.referral', [
            'code' => strtoupper($code),
            'type' => $r['type'],
            'vendorName' => $r['seller']?->displayName(),
            'workerName' => $r['worker']?->user?->name,
            'siteUrl' => $siteUrl,
            'kitPrice' => \App\Models\WelcomeKit::active()?->price ?? 250,
            'logo' => DocumentService::imageDataUri(DocumentService::logoPath()),
        ]);
    }

    public function verify(string $code)
    {
        $code = strtoupper(trim($code));
        $result = null;
        if ($m = NetworkMember::with(['user', 'parent.user', 'seller'])->where('referral_code', $code)->first()) {
            $result = [
                'kind' => $m->isAgent() ? 'BSO' : 'BJO',
                'name' => $m->user->name ?? '',
                'status' => $m->status,
                'since' => $m->created_at?->format('d M Y'),
                'extra' => $m->isAgent() ? ('Reports to: ' . ($m->parent?->user?->name ?? 'SmartBazars Head Office')) : ('Vendor: ' . ($m->seller?->displayName() ?? '—')),
            ];
        } elseif ($s = Seller::with('user')->where('referral_code', $code)->first()) {
            $v = $s->verification_status;
            $v = is_object($v) && property_exists($v, 'value') ? $v->value : (string)$v;
            $result = [
                'kind' => 'Vendor',
                'name' => $s->displayName(),
                'status' => $v === 'approved' ? 'active' : $v,
                'since' => ($s->verified_mail_sent_at ?? $s->created_at)?->format('d M Y'),
                'extra' => trim(($s->city ?? '') . ', ' . ($s->state ?? ''), ', '),
            ];
        } elseif ($c = UserCard::with('user')->where('card_number', $code)->first()) {
            $result = [
                'kind' => 'Member (User Card)',
                'name' => $c->user->name ?? '',
                'status' => $c->status === 'active' && $c->valid_upto?->endOfDay()->isFuture() ? 'active' : $c->status,
                'since' => $c->issued_at?->format('d M Y'),
                'extra' => 'Valid till ' . $c->valid_upto?->format('d M Y'),
            ];
        }
        return view('smartbazars.public.verify', [
            'code' => $code,
            'result' => $result,
            'logo' => DocumentService::imageDataUri(DocumentService::logoPath()),
        ]);
    }
}
