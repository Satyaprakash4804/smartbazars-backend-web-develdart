<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An BSO (type=agent) or a Vendor worker (type=worker).
 * BSOs form a tree via parent_id; workers belong to a seller (vendor).
 */
class NetworkMember extends Model
{
    use SoftDeletes;

    public const TYPE_AGENT = 'agent';
    public const TYPE_WORKER = 'worker';

    protected $fillable = [
        'user_id', 'type', 'parent_id', 'seller_id', 'level', 'path', 'referral_code', 'status',
        'created_by_type', 'created_by_id', 'wallet_balance', 'total_earned',
        'deleted_by', 'deleted_snapshot',
    ];

    protected $casts = [
        'wallet_balance' => 'decimal:2',
        'total_earned' => 'decimal:2',
        'level' => 'integer',
        'deleted_snapshot' => 'array',
    ];

    // withTrashed: an archived member keeps its (tombstoned) user so history pages still show the name
    public function user(): BelongsTo { return $this->belongsTo(User::class)->withTrashed(); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function kyc(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(NetworkMemberKyc::class, 'network_member_id');
    }

    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
    public function seller(): BelongsTo { return $this->belongsTo(Seller::class); }
    public function vendors(): HasMany { return $this->hasMany(Seller::class, 'agent_member_id'); }
    public function commissions(): HasMany { return $this->hasMany(NetworkCommission::class); }
    public function withdrawals(): HasMany { return $this->hasMany(NetworkWithdrawalRequest::class); }
    public function dailyReports(): HasMany { return $this->hasMany(PartnerDailyReport::class); }
    public function customers(): HasMany { return $this->hasMany(User::class, 'referred_by_worker_id'); }

    public function isAgent(): bool { return $this->type === self::TYPE_AGENT; }
    public function isWorker(): bool { return $this->type === self::TYPE_WORKER; }
    public function isActive(): bool { return $this->status === 'active'; }

    /** All agents below this agent (any depth). */
    public function scopeDescendantsOf($q, NetworkMember $m)
    {
        return $q->where('path', 'like', $m->path . $m->id . '/%')->orWhere('parent_id', $m->id);
    }

    public static function generateReferralCode(string $type): string
    {
        $prefix = $type === self::TYPE_AGENT ? 'SBA' : 'SBW';
        do {
            $code = $prefix . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        } while (self::withTrashed()->where('referral_code', $code)->exists());
        return $code;
    }
}
