<?php

namespace App\Services\SmartBazars;

use App\Models\NetworkMember;
use App\Models\Seller;
use App\Notifications\SmartBazars\VendorVerifiedNotification;
use App\Support\SmartBazars\Pdf;
use App\Support\SmartBazars\Photo;
use App\Support\SmartBazars\Qr;
use Illuminate\Support\Facades\Log;

/**
 * Official SmartBazars documents:
 *  - Vendor Authority Letter (PDF) + referral QR, emailed when admin verifies the vendor
 *  - BSO / BJO ID card (PDF), attached to the credentials email and downloadable in /partner
 */
class DocumentService
{
    public function __construct(protected ReferralService $referrals) {}

    public static function logoPath(): string
    {
        foreach (['logos/smartbazars/logo.png', 'logos/hyper-local-logo.png'] as $p) {
            if (is_file(public_path($p))) return public_path($p);
        }
        return '';
    }

    public static function imageDataUri(string $path): string
    {
        if (!$path || !is_file($path)) return '';
        $mime = str_ends_with(strtolower($path), '.png') ? 'image/png' : 'image/jpeg';
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
    }

    public function vendorData(Seller $seller): array
    {
        $seller->loadMissing(['user', 'agent.user']);
        $code = $seller->ensureReferralCode();
        $link = $this->referrals->referralLink($code);
        $verify = $this->referrals->verifyLink($code);
        $store = $seller->stores()->orderBy('id')->first();
        return [
            'seller' => $seller,
            'store' => $store,
            'name' => $seller->displayName(),
            'owner' => $seller->user->name ?? '',
            'code' => $code,
            'link' => $link,
            'verifyLink' => $verify,
            'qr' => Qr::dataUri($link, 300),
            'qrSvg' => Qr::svg($link, 220),
            'logo' => self::imageDataUri(self::logoPath()),
            'letterNo' => 'SB/AUTH/' . now()->format('Y') . '/' . str_pad((string)$seller->id, 5, '0', STR_PAD_LEFT),
            'issued' => ($seller->verified_mail_sent_at ?? now())->format('d M Y'),
            'validTill' => ($seller->verified_mail_sent_at ?? now())->copy()->addYear()->format('d M Y'),
            'agentName' => $seller->agent?->user?->name,
            'address' => trim(implode(', ', array_filter([$seller->address, $seller->landmark, $seller->city, $seller->state, $seller->zipcode]))),
        ];
    }

    public function authorityLetterPdf(Seller $seller): string
    {
        return Pdf::fromView('smartbazars.pdf.authority-letter', $this->vendorData($seller));
    }

    public function memberData(NetworkMember $member): array
    {
        $member->loadMissing(['user', 'parent.user', 'seller.user']);
        $verify = $this->referrals->verifyLink($member->referral_code);
        return [
            'member' => $member,
            'name' => $member->user->name ?? '',
            'role' => $member->isAgent() ? 'BSO' : 'BJO',
            'code' => $member->referral_code,
            'mobile' => $member->user->mobile ?? '',
            'email' => $member->user->email ?? '',
            'reportsTo' => $member->isAgent()
                ? ($member->parent?->user?->name ?? 'SmartBazars Head Office')
                : ($member->seller?->displayName() ?? '—'),
            'issued' => $member->created_at?->format('d M Y'),
            'validTill' => $member->created_at?->copy()->addYears(2)->format('d M Y'),
            'idNo' => 'SB-' . ($member->isAgent() ? 'AG' : 'WK') . '-' . str_pad((string)$member->id, 6, '0', STR_PAD_LEFT),
            'verifyLink' => $verify,
            'qr' => Qr::dataUri($verify, 240),
            'qrSvg' => Qr::svg($verify, 150),
            'logo' => self::imageDataUri(self::logoPath()),
            'photoUrl' => Photo::url($member->user),
            'initials' => collect(explode(' ', $member->user->name ?? 'S B'))->filter()->take(2)->map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode(''),
        ];
    }

    public function idCardPdf(NetworkMember $member): string
    {
        $data = $this->memberData($member);
        $photo = Photo::forUser($member->user);
        $data['photo'] = $photo ? Photo::cover($photo, 58, 74) : null;
        return Pdf::fromView('smartbazars.pdf.id-card', $data);
    }

    /** Email the vendor their authority letter + QR (once, unless $force). */
    public function sendVendorWelcome(Seller $seller, bool $force = false): bool
    {
        if (!$force && $seller->verified_mail_sent_at) {
            return false;
        }
        try {
            $seller->forceFill(['verified_mail_sent_at' => $seller->verified_mail_sent_at ?? now()])->saveQuietly();
            $data = $this->vendorData($seller);
            $pdf = $this->authorityLetterPdf($seller);
            $png = Qr::png($data['link'], 600);
            $seller->user?->notify(new VendorVerifiedNotification($data, $pdf, $png));
            return true;
        } catch (\Throwable $e) {
            Log::error('SmartBazars vendor welcome mail failed: ' . $e->getMessage(), ['seller_id' => $seller->id]);
            return false;
        }
    }
}
