<?php

namespace App\Services\SmartBazars;

use App\Models\NetworkMember;
use App\Models\NetworkMemberKyc;
use App\Models\NetworkRegistrationRequest;
use App\Models\Seller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * First-login KYC for BSOs, BJOs and Vendors. Only what is MISSING is asked — anything already known
 * (e.g. from the Apply-now form or the vendor registration) is never asked again.
 */
class KycService
{
    public const MEMBER_DOCS = ['aadhar' => 'Aadhaar card', 'pan' => 'PAN card', 'education' => 'Education certificate'];
    public const SELLER_DOCS = [
        'business_license' => 'Business License',
        'articles_of_incorporation' => 'Articles of Incorporation',
        'national_identity_card' => 'National Identity Card',
        'authorized_signature' => 'Authorized Signature',
    ];

    // ---------------------------------------------------------------- agent / worker
    /** KYC row for a member; for people who applied via "Apply now" it is built from their application. */
    public function kycFor(NetworkMember $m): ?NetworkMemberKyc
    {
        if ($m->kyc) return $m->kyc;

        $req = NetworkRegistrationRequest::where('created_user_id', $m->user_id)->where('source', 'public')->whereNotNull('documents')->latest()->first();
        if (!$req) return null;

        $meta = $req->meta ?? [];
        $kyc = NetworkMemberKyc::create([
            'network_member_id' => $m->id,
            'address' => $meta['address'] ?? null, 'city' => $meta['city'] ?? null, 'state' => $meta['state'] ?? null, 'zipcode' => $meta['zipcode'] ?? null,
            'aadhar_number' => $meta['aadhar_number'] ?? null, 'pan_number' => $meta['pan_number'] ?? null, // already encrypted
            'qualification' => $meta['qualification'] ?? null, 'education_details' => $meta['education_details'] ?? null,
            'documents' => $req->documents,
        ]);
        $m->setRelation('kyc', $kyc);
        return $kyc;
    }

    /** @return string[] field names still needed (same names as the form inputs) */
    public function memberMissing(NetworkMember $m): array
    {
        $u = $m->user;
        $k = $this->kycFor($m);
        $miss = [];
        foreach (['name', 'mobile', 'email'] as $f) {
            if (blank($u?->{$f})) $miss[] = $f;
        }
        foreach (['address', 'city', 'state', 'zipcode', 'qualification'] as $f) {
            if (blank($k?->{$f})) $miss[] = $f;
        }
        foreach (['aadhar_number', 'pan_number'] as $f) {
            if (blank($k?->getRawOriginal($f))) $miss[] = $f;
        }
        foreach (array_keys(self::MEMBER_DOCS) as $d) {
            $path = $k?->documents[$d] ?? null;
            if (!$path || !Storage::disk('local')->exists($path)) $miss[] = $d . '_doc';
        }
        return $miss;
    }

    public function saveMember(NetworkMember $m, array $data, array $files): void
    {
        DB::transaction(function () use ($m, $data, $files) {
            $userFields = array_intersect_key($data, array_flip(['name', 'mobile', 'email']));
            if (isset($userFields['email'])) $userFields['email'] = strtolower(trim($userFields['email']));
            if ($userFields) $m->user->update($userFields);

            $kyc = $this->kycFor($m) ?? new NetworkMemberKyc(['network_member_id' => $m->id]);
            foreach (['address', 'city', 'state', 'zipcode', 'qualification', 'education_details'] as $f) {
                if (array_key_exists($f, $data) && filled($data[$f])) $kyc->{$f} = trim($data[$f]);
            }
            if (!empty($data['aadhar_number'])) $kyc->aadhar_number = Crypt::encryptString(preg_replace('/\s+/', '', $data['aadhar_number']));
            if (!empty($data['pan_number'])) $kyc->pan_number = Crypt::encryptString(strtoupper($data['pan_number']));

            $docs = $kyc->documents ?? [];
            foreach (array_keys(self::MEMBER_DOCS) as $d) {
                if ($file = $files[$d . '_doc'] ?? null) {
                    if (!empty($docs[$d])) Storage::disk('local')->delete($docs[$d]);
                    $docs[$d] = $file->storeAs('smartbazars/kyc/member-' . $m->id, $d . '-' . time() . '.' . $file->getClientOriginalExtension(), 'local');
                }
            }
            $kyc->documents = $docs;
            $kyc->save();
        });
    }

    // ---------------------------------------------------------------- vendor
    /** @return string[] */
    public function sellerMissing(Seller $s): array
    {
        $u = $s->user;
        $miss = [];
        foreach (['name', 'mobile', 'email'] as $f) {
            if (blank($u?->{$f})) $miss[] = $f;
        }
        foreach (['country', 'address', 'city', 'state', 'zipcode'] as $f) {
            if (blank($s->{$f})) $miss[] = $f;
        }
        foreach (array_keys(self::SELLER_DOCS) as $c) {
            if (!$s->hasMedia($c)) $miss[] = $c;
        }
        return $miss;
    }

    public const SELLER_DOC_HELP = [
        'business_license' => 'Upload a clear copy of your business license. Accepted formats: JPEG, PNG, PDF. Max size: 2MB.',
        'articles_of_incorporation' => "Provide your company's articles of incorporation or certificate of incorporation. File must be clear and readable.",
        'national_identity_card' => "Upload a government-issued photo ID (passport, driver's license, or national ID card). Both front and back sides if applicable.",
        'authorized_signature' => 'Upload a document with authorized signature samples or signature authorization letter from your company.',
    ];

    /** Validation rules for the vendor KYC form (web + app share these). Only MISSING fields are required. */
    public function sellerRules(Seller $s, array $missing): array
    {
        $uid = $s->user_id;
        $doc = 'required|file|mimes:jpeg,jpg,png,pdf,webp|max:2048';
        $all = [
            'name' => 'required|string|max:100',
            'mobile' => ['required', 'string', 'min:10', 'max:20', \Illuminate\Validation\Rule::unique('users', 'mobile')->ignore($uid)->whereNull('deleted_at')],
            'email' => ['required', 'email', 'max:150', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($uid)->whereNull('deleted_at')],
            'country' => 'required|string|max:100',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'zipcode' => 'required|string|max:20',
            'business_license' => $doc,
            'articles_of_incorporation' => $doc,
            'national_identity_card' => $doc,
            'authorized_signature' => $doc,
        ];
        return array_intersect_key($all, array_flip($missing)) + ['landmark' => 'nullable|string|max:100'];
    }

    /** What the vendor app needs to render the KYC screen. */
    public function sellerStatus(Seller $s): array
    {
        $missing = $this->sellerMissing($s);
        $docs = [];
        foreach (self::SELLER_DOCS as $key => $label) {
            $docs[] = ['key' => $key, 'label' => $label, 'help' => self::SELLER_DOC_HELP[$key], 'uploaded' => $s->hasMedia($key)];
        }
        return [
            'complete' => !$missing,
            'missing' => $missing,
            'values' => [
                'name' => $s->user->name, 'mobile' => $s->user->mobile, 'email' => $s->user->email,
                'country' => $s->country, 'address' => $s->address, 'city' => $s->city,
                'landmark' => $s->landmark, 'state' => $s->state, 'zipcode' => $s->zipcode,
            ],
            'documents' => $docs,
        ];
    }

    public function saveSeller(Seller $s, array $data, array $files): void
    {
        DB::transaction(function () use ($s, $data, $files) {
            $userFields = array_intersect_key($data, array_flip(['name', 'mobile', 'email']));
            if (isset($userFields['email'])) $userFields['email'] = strtolower(trim($userFields['email']));
            if ($userFields) $s->user->update($userFields);

            $sellerFields = array_intersect_key($data, array_flip(['country', 'address', 'city', 'landmark', 'state', 'zipcode']));
            $sellerFields = array_filter($sellerFields, fn($v) => filled($v));
            if ($sellerFields) $s->update($sellerFields);

            foreach (array_keys(self::SELLER_DOCS) as $c) {
                if (!empty($files[$c])) $s->addMedia($files[$c])->toMediaCollection($c);
            }
        });
    }
}
