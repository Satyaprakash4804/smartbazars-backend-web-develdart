<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Extra details for a customer whose User Card was created offline by a vendor or BJO. */
class OfflineCustomerProfile extends Model
{
    protected $fillable = [
        'user_id', 'user_card_id', 'created_by_type', 'created_by_id', 'seller_id', 'network_member_id',
        'address_line', 'ward_panchayat', 'block', 'district', 'state', 'pincode',
        'aadhaar_last4', 'aadhaar_file', 'photo_file', 'share_token', 'card_emailed_at',
    ];
    protected $casts = ['card_emailed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $p) {
            $p->share_token = $p->share_token ?: Str::random(40);
        });
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class)->withTrashed(); }
    public function card(): BelongsTo { return $this->belongsTo(UserCard::class, 'user_card_id'); }
    public function seller(): BelongsTo { return $this->belongsTo(Seller::class); }
    public function member(): BelongsTo { return $this->belongsTo(NetworkMember::class, 'network_member_id'); }

    public function fullAddress(): string
    {
        return implode(', ', array_filter([
            $this->address_line, $this->ward_panchayat, $this->block, $this->district, $this->state, $this->pincode,
        ]));
    }
}
