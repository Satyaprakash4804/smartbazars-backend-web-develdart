<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A joining fee. Never creates commissions — the full amount belongs to the company. */
class NetworkFeePayment extends Model
{
    protected $fillable = [
        'uuid', 'purpose', 'registration_request_id', 'seller_id', 'payer_name', 'payer_email', 'amount', 'currency',
        'status', 'razorpay_order_id', 'razorpay_payment_id', 'paid_at', 'return_url', 'meta',
    ];

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'meta' => 'array'];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            $m->uuid = $m->uuid ?: (string)Str::uuid();
        });
    }

    public function request(): BelongsTo { return $this->belongsTo(NetworkRegistrationRequest::class, 'registration_request_id'); }
    public function seller(): BelongsTo { return $this->belongsTo(Seller::class); }

    public function isPaid(): bool { return $this->status === 'paid'; }
    public function isSettled(): bool { return in_array($this->status, ['paid', 'waived'], true); }
    public function payUrl(): string { return route('network-fee.pay', $this->uuid); }
}
