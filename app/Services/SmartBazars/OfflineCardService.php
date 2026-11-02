<?php

namespace App\Services\SmartBazars;

use App\Models\NetworkMember;
use App\Models\OfflineCustomerProfile;
use App\Models\Seller;
use App\Models\User;
use App\Models\UserCard;
use App\Notifications\SmartBazars\OfflineUserCardNotification;
use App\Support\SmartBazars\Pdf;
use App\Support\SmartBazars\Qr;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates a User Card for an offline customer (counter / BJO), builds the printable card (HTML + PDF) and emails it.
 * The customer is a real `users` row (access_panel=web), so coins, wallet, orders and the app login all keep working.
 */
class OfflineCardService
{
    public const DISK = 'local'; // private disk: photo + Aadhaar are never publicly reachable

    public function __construct(protected WelcomeKitService $cards, protected ReferralService $referrals) {}

    /**
     * @param array{name:string,mobile:string,email:string,address_line?:?string,ward_panchayat:string,block:string,district:string,state:string,pincode?:?string,aadhaar_last4?:?string} $data
     * @param array{type:string,id:?int,seller_id:?int,member_id:?int,user_id:?int} $creator
     */
    public function create(array $data, ?UploadedFile $photo, ?UploadedFile $aadhaar, array $creator): OfflineCustomerProfile
    {
        $mobile = app(\App\Services\OtpService::class)->sanitizeMobile($data['mobile']);
        if (strlen($mobile) !== 10) {
            throw ValidationException::withMessages(['mobile' => 'Enter a valid 10-digit mobile number.']);
        }
        if (User::withTrashed()->where('mobile', $mobile)->exists()) {
            throw ValidationException::withMessages(['mobile' => 'A customer with this mobile number already exists. Use "Find customer" to open their card.']);
        }
        if (User::withTrashed()->where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages(['email' => 'This email is already registered.']);
        }

        $password = 'Sb@' . Str::random(7);

        $profile = DB::transaction(function () use ($data, $mobile, $password, $photo, $aadhaar, $creator) {
            $user = User::create([
                'name' => trim($data['name']),
                'mobile' => $mobile,
                'email' => strtolower(trim($data['email'])),
                'password' => $password,
                'status' => true,
                'access_panel' => 'web',
                'referral_code' => strtoupper(Str::random(8)),
                'referred_by_worker_id' => $creator['member_id'] ?? null,
                'referred_by_seller_id' => $creator['seller_id'] ?? null,
            ]);

            $card = $this->cards->issueCard($user);

            $dir = 'offline-customers/' . $user->id;
            $photoPath = $photo ? $photo->storeAs($dir, 'photo-' . Str::random(8) . '.' . ($photo->guessExtension() ?: 'jpg'), self::DISK) : null;
            $aadhaarPath = $aadhaar ? $aadhaar->storeAs($dir, 'aadhaar-' . Str::random(8) . '.' . ($aadhaar->guessExtension() ?: 'jpg'), self::DISK) : null;

            return OfflineCustomerProfile::create([
                'user_id' => $user->id,
                'user_card_id' => $card->id,
                'created_by_type' => $creator['type'],
                'created_by_id' => $creator['id'] ?? null,
                'seller_id' => $creator['seller_id'] ?? null,
                'network_member_id' => $creator['member_id'] ?? null,
                'address_line' => $data['address_line'] ?? null,
                'ward_panchayat' => $data['ward_panchayat'],
                'block' => $data['block'],
                'district' => $data['district'],
                'state' => $data['state'],
                'pincode' => $data['pincode'] ?? null,
                'aadhaar_last4' => !empty($data['aadhaar_last4']) ? substr(preg_replace('/\D/', '', $data['aadhaar_last4']), -4) : null,
                'aadhaar_file' => $aadhaarPath,
                'photo_file' => $photoPath,
            ]);
        });

        $this->email($profile, $password);
        return $profile->fresh(['user', 'card']);
    }

    /** Everything the card view / PDF needs. */
    public function cardData(OfflineCustomerProfile $p): array
    {
        $p->loadMissing(['user', 'card']);
        $card = $p->card ?: UserCard::where('user_id', $p->user_id)->first();
        // Same QR as the online card: machine line + the holder's details printed on the card
        $payload = $card->qrPayload();
        return [
            'profile' => $p,
            'card' => $card,
            'name' => $p->user->name,
            'cardNo' => $card->formatted_number,
            'rawCardNo' => $card->card_number,
            'mobile' => $p->user->mobile,
            'email' => $p->user->email,
            'ward' => $p->ward_panchayat,
            'block' => $p->block,
            'district' => $p->district,
            'state' => $p->state,
            'validUpto' => $card->valid_upto?->format('d M Y'),
            'photoUri' => $this->photoDataUri($p),
            'photo' => $this->photoCover($p, 163, 192),   // dompdf has no object-fit: pre-computed crop (PDF scale 0.5)
            'hdrUri' => $this->assetUri('images/user-card/header.jpg'),
            'ftrUri' => $this->assetUri('images/user-card/footer.jpg'),
            'qrSvg' => Qr::svg($payload, 220),
            'qrUri' => Qr::dataUri($payload, 260),
            'logo' => DocumentService::imageDataUri(DocumentService::logoPath()),
            'shareUrl' => route('offline.card.public', $p->share_token),
        ];
    }

    protected function assetUri(string $publicPath): string
    {
        $f = public_path($publicPath);
        return is_file($f) ? 'data:' . (str_ends_with($f, '.png') ? 'image/png' : 'image/jpeg') . ';base64,' . base64_encode(file_get_contents($f)) : '';
    }

    /** @return array{uri:string,w:int,h:int,iw:float,ih:float,left:float,top:float}|null */
    public function photoCover(OfflineCustomerProfile $p, int $bw, int $bh): ?array
    {
        if (!$p->photo_file || !Storage::disk(self::DISK)->exists($p->photo_file)) return null;
        $tmp = \App\Support\SmartBazars\Photo::normalize(Storage::disk(self::DISK)->path($p->photo_file));
        $bin = @file_get_contents($tmp);
        $info = $bin ? @getimagesizefromstring($bin) : false;
        if (!$info) return null;
        return \App\Support\SmartBazars\Photo::cover([
            'uri' => 'data:' . $info['mime'] . ';base64,' . base64_encode($bin), 'w' => (int)$info[0], 'h' => (int)$info[1],
        ], $bw, $bh);
    }

    public function photoDataUri(OfflineCustomerProfile $p): string
    {
        if (!$p->photo_file || !Storage::disk(self::DISK)->exists($p->photo_file)) return '';
        $abs = Storage::disk(self::DISK)->path($p->photo_file);
        $tmp = \App\Support\SmartBazars\Photo::normalize($abs);
        return DocumentService::imageDataUri($tmp);
    }

    public function pdf(OfflineCustomerProfile $p): string
    {
        // 754 x 488 px page (= 565.5 x 366 pt) keeps the 1508:976 proportion of the SmartBazars card artwork
        return Pdf::fromView('offline.pdf.user-card', $this->cardData($p), [0, 0, 565.5, 366], 'portrait');
    }

    public function email(OfflineCustomerProfile $p, ?string $password = null): bool
    {
        try {
            $p->loadMissing('user');
            $data = $this->cardData($p);
            $p->user->notify(new OfflineUserCardNotification($data, $this->pdf($p), $password, $data['shareUrl']));
            $p->forceFill(['card_emailed_at' => now()])->saveQuietly();
            return true;
        } catch (\Throwable $e) {
            Log::error('Offline user card email failed: ' . $e->getMessage(), ['profile_id' => $p->id]);
            return false;
        }
    }

    /** wa.me link with the public card URL; the vendor/BJO taps it and WhatsApp opens with the message ready. */
    public function whatsappLink(OfflineCustomerProfile $p): string
    {
        $p->loadMissing(['user', 'card']);
        $text = "Namaste {$p->user->name}, your SmartBazars User Card ({$p->card->formatted_number}) is ready.\n"
            . "View / download: " . route('offline.card.public', $p->share_token) . "\nSmart Shopping, Better Life.";
        return 'https://wa.me/91' . $p->user->mobile . '?text=' . rawurlencode($text);
    }

    /** Who may see this customer? vendors: their own + referred; BJO: own; admin: all. */
    public function visibleTo(OfflineCustomerProfile $p, string $who, ?Seller $seller = null, ?NetworkMember $member = null): bool
    {
        return match ($who) {
            'admin' => true,
            'seller' => $seller && (int)$p->seller_id === (int)$seller->id,
            'worker' => $member && (int)$p->network_member_id === (int)$member->id,
            default => false,
        };
    }
}
