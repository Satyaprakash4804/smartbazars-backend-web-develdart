<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkRegistrationRequest extends Model
{
    protected $fillable = [
        'type', 'name', 'email', 'mobile', 'meta', 'requested_by_member_id', 'requested_by_seller_id',
        'seller_id', 'status', 'seller_approved_at', 'admin_approved_at', 'approved_by', 'rejection_reason',
        'created_user_id', 'uuid', 'source', 'documents', 'payment_status', 'fee_amount', 'auto_approve', 'reapplied_from_id',
    ];

    protected $casts = [
        'meta' => 'array',
        'documents' => 'array',
        'auto_approve' => 'boolean',
        'fee_amount' => 'decimal:2',
        'seller_approved_at' => 'datetime',
        'admin_approved_at' => 'datetime',
    ];

    public function requestedByMember(): BelongsTo { return $this->belongsTo(NetworkMember::class, 'requested_by_member_id'); }
    public function requestedBySeller(): BelongsTo { return $this->belongsTo(Seller::class, 'requested_by_seller_id'); }
    public function seller(): BelongsTo { return $this->belongsTo(Seller::class, 'seller_id'); }

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            $m->uuid = $m->uuid ?: (string)\Illuminate\Support\Str::uuid();
        });
    }

    public function feePayment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(NetworkFeePayment::class, 'registration_request_id')->latestOfMany();
    }

    /** Aadhaar / PAN numbers are stored encrypted inside meta. */
    public function sensitive(string $key): ?string
    {
        $v = $this->meta[$key] ?? null;
        if (!$v) return null;
        try { return \Illuminate\Support\Facades\Crypt::decryptString($v); } catch (\Throwable) { return null; }
    }

    public function paymentSettled(): bool
    {
        return in_array($this->payment_status, ['not_required', 'paid', 'waived'], true);
    }

    public function requesterLabel(): string
    {
        if ($this->requestedByMember) {
            return ucfirst($this->requestedByMember->type) . ': ' . ($this->requestedByMember->user->name ?? '-') . ' (' . $this->requestedByMember->referral_code . ')';
        }
        if ($this->requestedBySeller) {
            return 'Vendor: ' . ($this->requestedBySeller->user->name ?? ('#' . $this->requested_by_seller_id));
        }
        return match ($this->source) { 'public' => 'Self-applied (Apply now)', default => 'Admin' };
    }
}
