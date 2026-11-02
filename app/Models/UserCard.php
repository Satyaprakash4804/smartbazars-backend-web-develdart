<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UserCard extends Model
{
    protected $fillable = ['user_id', 'welcome_kit_purchase_id', 'card_number', 'issued_at', 'valid_upto', 'status'];
    protected $casts = ['issued_at' => 'date', 'valid_upto' => 'date'];

    /** Prefix kept as the FIRST line of every QR so all scanners (POS, BJO, apps) keep recognising the card. */
    public const QR_PREFIX = 'SMARTBAZARS:CARD:';

    public function user() { return $this->belongsTo(User::class); }

    /** "SB2026001234" -> "SB 2026 0012 34" (as printed on the card) */
    public function getFormattedNumberAttribute(): string
    {
        $n = $this->card_number;
        return trim(substr($n, 0, 2) . ' ' . substr($n, 2, 4) . ' ' . substr($n, 6, 4) . ' ' . substr($n, 10));
    }

    public function isValidNow(): bool
    {
        return $this->status === 'active' && (bool)$this->valid_upto?->copy()->endOfDay()->isFuture();
    }

    /**
     * Extracts the card number from anything a scanner returns: the multi-line QR payload,
     * the legacy "SMARTBAZARS:CARD:XXXX" payload or a plain/formatted card number.
     */
    public static function cardNumberFromScan(string $text): string
    {
        if (preg_match('/SMARTBAZARS:CARD:\s*([A-Z0-9]+)/i', $text, $m)) {
            return strtoupper($m[1]);
        }
        $firstLine = strtok(trim($text), "\r\n") ?: '';
        return strtoupper(preg_replace('/[\s\-]+/', '', $firstLine));
    }

    /**
     * What the QR on BOTH the online (app/web) and the offline (printed) card encodes.
     * Line 1 stays machine-readable; the rest is the holder's details, readable by any phone camera,
     * exactly as printed on the offline card.
     */
    public function qrPayload(): string
    {
        $this->loadMissing('user');
        $user = $this->user;
        $profile = $user ? OfflineCustomerProfile::where('user_id', $user->id)->first() : null;
        $lim = fn($v, $n = 40) => Str::limit(trim((string)$v), $n, '');

        $lines = [
            self::QR_PREFIX . $this->card_number,
            'Name: ' . $lim($user->name ?? ''),
            'Card No: ' . $this->formatted_number,
        ];
        if (!empty($user?->mobile)) {
            $lines[] = 'Mobile: ' . $user->mobile;
        }

        if ($profile) {
            if ($profile->ward_panchayat) $lines[] = 'Ward/Panchayat: ' . $lim($profile->ward_panchayat);
            $bd = trim(implode(', ', array_filter([$profile->block, $profile->district])));
            if ($bd !== '') $lines[] = 'Block/District: ' . $lim($bd, 44);
            if ($profile->state) $lines[] = 'State: ' . $lim($profile->state, 30);
        } elseif ($user) {
            $addr = Address::where('user_id', $user->id)->latest('id')->first();
            if ($addr) {
                $place = trim(implode(', ', array_filter([$addr->city, $addr->state, $addr->zipcode])));
                if ($place !== '') $lines[] = 'Address: ' . $lim($place, 50);
            }
        }

        $lines[] = 'Valid upto: ' . ($this->valid_upto?->format('d-m-Y') ?? '-');
        $lines[] = 'Verify: ' . app(\App\Services\SmartBazars\ReferralService::class)->verifyLink($this->card_number);

        return implode("\n", array_filter($lines, fn($l) => $l !== null && $l !== ''));
    }

    public function toApiArray(): array
    {
        $qr = $this->qrPayload();

        return [
            'card_number' => $this->card_number,
            'formatted_number' => $this->formatted_number,
            'holder_name' => $this->user->name ?? '',
            'issued_at' => $this->issued_at?->format('d-m-Y'),
            'valid_upto' => $this->valid_upto?->format('d-m-Y'),
            'status' => $this->status,
            'is_valid' => $this->isValidNow(),
            // QR payload (same as the printed offline card): line 1 "SMARTBAZARS:CARD:<no>" for scanners,
            // then the holder's details so any phone camera shows who the card belongs to.
            'qr_data' => $qr,
            'tagline_hi' => 'स्मार्ट खरीदारी, बेहतर जीवन',
            'qr_svg' => \App\Support\SmartBazars\Qr::svg($qr, 200),
            'verify_url' => app(\App\Services\SmartBazars\ReferralService::class)->verifyLink($this->card_number),
        ];
    }
}
