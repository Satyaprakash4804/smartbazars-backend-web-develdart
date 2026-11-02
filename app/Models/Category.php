<?php

namespace App\Models;

use App\Enums\Category\CategoryBackgroundTypeEnum;
use App\Enums\SpatieMediaCollectionName;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @method static findOrFail($id)
 * @method static create(mixed $validated)
 * @method static where(string $string, string $string1, string $string2)
 * @method static count()
 */
class Category extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia;

    protected $appends = ['image', 'banner', 'icon', 'active_icon', 'background_image'];

    protected $fillable = [
        'uuid',
        'parent_id',
        'title',
        'slug',
        'description',
        'status',
        'requires_approval',
        'commission',
        'sort_order',
        'is_home_category',
        'background_type',
        'background_color',
        'font_color',
        'metadata'
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
        'is_home_category' => 'boolean',
        'commission' => 'decimal:2',
        'background_type' => CategoryBackgroundTypeEnum::class,
        'metadata' => 'array',
    ];

    /**
     * Get the featured sections associated with the category.
     */
    public function featuredSections(): BelongsToMany
    {
        return $this->belongsToMany(FeaturedSection::class, 'category_featured_section')
            ->withTimestamps();
    }

    public function setTitleAttribute($value): void
    {
        $this->attributes['title'] = $value;
        $this->attributes['slug'] = generateUniqueSlug(self::class, $value);
        $this->attributes['uuid'] = (string)Str::uuid();
    }

    public function setCommissionAttribute($value): void
    {
        $this->attributes['commission'] = empty($value) ? 0 : $value;
    }

    public function getImageAttribute(): ?string
    {
        return (!empty($this->getFirstMediaUrl('image')) ? $this->getFirstMediaUrl('image') : asset('assets/images/category-placeholder.png'));
    }

    public function getBannerAttribute(): ?string
    {
        return $this->getFirstMediaUrl('banner');
    }

    public function getIconAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::CATEGORY_ICON());
    }

    public function getActiveIconAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::CATEGORY_ACTIVE_ICON());
    }

    public function getBackgroundImageAttribute(): ?string
    {
        return $this->getFirstMediaUrl(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE());
    }

    // Relationships
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    // Scopes
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public function getCategoryHierarchy(): array
    {
        $hierarchy = [];
        $current = $this;

        while ($current) {
            array_unshift($hierarchy, [
                'id' => $current->id,
                'uuid' => $current->uuid,
                'title' => $current->title,
                'slug' => $current->slug
            ]);
            $current = $current->parent;
        }

        return $hierarchy;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
        $this->addMediaCollection('banner')->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::CATEGORY_ICON())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::CATEGORY_ACTIVE_ICON())->singleFile();
        $this->addMediaCollection(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE())->singleFile();
    }

    protected static function booted(): void
    {
        // SmartBazars: a category the admin adds shows up for customers straight away –
        // it goes to the END of its level (not before the admin's sorted ones) and, when the
        // admin uses "home categories", a new top-level category is included on the home screen
        // (it can be unticked on the Home Categories page).
        static::creating(function (Category $category) {
            if (empty($category->sort_order)) {
                $category->sort_order = (int)static::query()
                        ->where('parent_id', $category->parent_id)
                        ->max('sort_order') + 1;
            }
            if (is_null($category->parent_id) && !$category->isDirty('is_home_category')) {
                $category->is_home_category = static::query()
                    ->whereNull('parent_id')
                    ->where('is_home_category', true)
                    ->exists();
            }
        });

        static::deleting(function ($category) {
            if ($category->products()->exists()) {
                throw new Exception(__('messages.category_cannot_be_deleted_with_products'));
            }
            // Prevent deletion if any direct child category has products assigned
            if ($category->children()->whereHas('products')->exists()) {
                throw new Exception(__('messages.category_cannot_be_deleted_with_products'));
            }
            $category->clearMediaCollection('image');
            $category->clearMediaCollection('banner');
            $category->clearMediaCollection(SpatieMediaCollectionName::CATEGORY_ICON());
            $category->clearMediaCollection(SpatieMediaCollectionName::CATEGORY_ACTIVE_ICON());
            $category->clearMediaCollection(SpatieMediaCollectionName::CATEGORY_BACKGROUND_IMAGE());
        });
    }
}
