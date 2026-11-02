<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class NetworkMemberKyc extends Model
{
    protected $table = 'network_member_kyc';

    protected $fillable = [
        'network_member_id', 'address', 'city', 'state', 'zipcode', 'aadhar_number', 'pan_number',
        'qualification', 'education_details', 'documents',
    ];

    protected $casts = ['documents' => 'array'];

    public function member(): BelongsTo { return $this->belongsTo(NetworkMember::class, 'network_member_id'); }

    /** Aadhaar / PAN numbers are stored encrypted. */
    public function sensitive(string $field): ?string
    {
        $v = $this->getRawOriginal($field);
        if (!$v) return null;
        try { return Crypt::decryptString($v); } catch (\Throwable) { return null; }
    }
}
