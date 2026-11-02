<?php

namespace App\Models;

use App\Enums\Seller\SellerVerificationStatusEnum;
use App\Enums\Seller\SellerVisibilityStatusEnum;
use App\Enums\SpatieMediaCollectionName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Enums\Subscription\SellerSubscriptionStatusEnum;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @method static where(string $string, $id)
 * @method static create(array $sellerData)
 * @method static find(string $id)
 * @method static count()
 * @method static findOrFail(mixed $id)
 */
class Seller extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    public function statements(): HasMany
    {
        return $this->hasMany(\App\Models\SellerStatement::class);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'address',
        'city',
        'landmark',
        'state',
        'zipcode',
        'country',
        'country_code',
        'latitude',
        'longitude',
        'business_license',
        'articles_of_incorporation',
        'national_identity_card',
        'authorized_signature',
        'verification_status',
        'metadata',
        'visibility_status',
        'agent_member_id',
        'is_house_store',
        'referral_code',
        'verified_mail_sent_at',
        'coin_balance',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'metadata' => 'array',
            'verified_mail_sent_at' => 'datetime',
            'is_house_store' => 'boolean',
            'deleted_snapshot' => 'array',
        ];
    }

    public function getVerificationStatusAttribute($value){
        return !empty($value) ? $value : SellerVerificationStatusEnum::NotApproved();
    }

    public function getVisibilityStatusAttribute($value)
    {
        return !empty($value) ? $value : SellerVisibilityStatusEnum::Draft();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function storeFollowingSellers(): HasMany
    {
        return $this->hasMany(FollowingSeller::class);
    }

    public function countryDetails(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country', 'name');
    }

    public function subscriptionPlan(): HasMany
    {
        return $this->hasMany(SellerSubscription::class);
    }

    /**
     * Get the seller's current active subscription (if any).
     */
    public function activeSubscription(): HasOne
    {
        $now = now();

        return $this->hasOne(SellerSubscription::class)
            ->where('status', SellerSubscriptionStatusEnum::ACTIVE())
            ->where(function ($q) use ($now) {
                $q->whereNull('start_date')
                  ->orWhere('start_date', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', $now);
            })
            ->latestOfMany('start_date');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(SpatieMediaCollectionName::BUSINESS_LICENSE())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::ARTICLES_OF_INCORPORATION())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::NATIONAL_IDENTITY_CARD())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::AUTHORIZED_SIGNATURE())->singleFile();
    }

    protected static function booted(): void
    {
        // SmartBazars v1.4: every vendor gets a referral code (used in QR / authority letter)
        static::creating(function (Seller $seller) {
            if (empty($seller->referral_code)) {
                $seller->referral_code = self::generateReferralCode();
            }
        });
        static::deleting(function ($category) {
            $category->clearMediaCollection(SpatieMediaCollectionName::BUSINESS_LICENSE());
            $category->clearMediaCollection(SpatieMediaCollectionName::ARTICLES_OF_INCORPORATION());
            $category->clearMediaCollection(SpatieMediaCollectionName::NATIONAL_IDENTITY_CARD());
            $category->clearMediaCollection(SpatieMediaCollectionName::AUTHORIZED_SIGNATURE());
        });
    }

    /** Agent who onboarded this vendor (SmartBazars network). */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(NetworkMember::class, 'agent_member_id');
    }

    public function workers(): HasMany
    {
        return $this->hasMany(NetworkMember::class, 'seller_id')->where('type', NetworkMember::TYPE_WORKER);
    }

    // ---- SmartBazars v1.4: vendor referral code / QR ----
    public static function generateReferralCode(): string
    {
        do {
            $c = 'SBV' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        } while (self::where('referral_code', $c)->exists());
        return $c;
    }

    public function ensureReferralCode(): string
    {
        if (!$this->referral_code) {
            $this->forceFill(['referral_code' => self::generateReferralCode()])->saveQuietly();
        }
        return $this->referral_code;
    }

    /** Display name for letters / QR posters: first store name, else metadata, else owner. */
    public function displayName(): string
    {
        $store = $this->stores()->orderBy('id')->value('name');
        return $store ?: ($this->metadata['store_name'] ?? ($this->user->name ?? ('Vendor #' . $this->id)));
    }

    public function referredCustomers(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by_seller_id');
    }
}
