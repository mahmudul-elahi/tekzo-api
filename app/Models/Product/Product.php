<?php

namespace App\Models\Product;

use App\Enums\StatusEnum;
use App\Models\Brand\Brand;
use App\Models\Care\ProductCare;
use App\Models\Category\Category;
use App\Models\Coupon\CouponProductScope;
use App\Models\Coupon\NewCoupon;
use App\Models\Gift\GiftProduct;
use App\Models\Offer\OfferProduct;
use App\Models\PreOwned\PreOwned;
use App\Models\PreOwned\PreOwnedProductSeo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use Searchable, SoftDeletes;

    public $casts = [
        'has_official_warranty' => 'boolean',
        'is_chinese' => 'boolean',
        'is_unofficial' => 'boolean',
        'is_refurbished' => 'boolean',
        'is_ecommerce' => 'boolean',
        'purchase_point' => 'integer',
        'booking_amount' => 'float:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('isEcommerce', function ($query) {
            $query->where('is_ecommerce', true);
        });
    }

    /**
     * Eager load the relations used by toSearchableArray() before indexing.
     *
     * @param  Collection<int, Product>  $models
     * @return Collection<int, Product>
     */
    public function makeSearchableUsing(Collection $models): Collection
    {
        return $models->loadMissing(['category:id,name', 'brand:id,name']);
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->product_name,
            'slug' => $this->slug,
            'headline' => $this->headline,
            'product_image' => $this->product_image,
            'short_description' => $this->short_description,
            'category' => optional($this->category)->name,
            'brand' => optional($this->brand)->name,
            'status' => $this->status,
            'name_words' => implode(' ', $this->tokenize($this->product_name ?? '')),
        ];
    }

    private function tokenize(string $text): array
    {
        // "Find My iPhone Case" → ["find", "my", "iphone", "case"]
        return array_filter(
            explode(' ', strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $text)))
        );
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id', 'id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    /*************  ✨ Windsurf Command ⭐  *************/
    /**
     * Get the product variants.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id', 'id');
    }

    public function attributes()
    {
        return $this->hasMany(ProductAttribute::class, 'product_id', 'id');
    }

    public function stockVariant()
    {
        return $this->hasOne(ProductVariant::class)
            ->orderBy('stock', 'desc');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)
            ->where('is_primary', true)
            ->with('attributeValue:id,value,attribute_id');
    }

    public function videos()
    {
        return $this->hasMany(ProductVideo::class, 'product_id', 'id');
    }

    public function getDescription()
    {
        $description = LongDescription::query()
            ->where('model_id', $this->id)
            ->where('model_type', 'App\Models\Pos\Product\Product')
            ->whereHas('tenant', fn ($query) => $query->where('slug', config('services.tenant_slug')))
            ->first();

        return $description->description ?? null;
    }

    public function specificationItems()
    {
        return $this->hasMany(ProductSpecification::class)
            ->with(['parent', 'sub']);
    }

    /**
     * Only approved, customer-visible reviews — admin-authored reviews
     * (is_admin_review) and unapproved ones are excluded here so every
     * withCount('reviews')/avg-rating call site is public-safe by default
     * without each one needing to remember the filter itself.
     */
    public function reviews()
    {
        return $this->hasMany(Review::class, 'product_id', 'id')
            ->where('status', StatusEnum::Active);
    }

    public function offerProduct()
    {
        return $this->hasOne(OfferProduct::class, 'product_id', 'id')
            ->whereHas('offer', function ($query) {
                $now = Carbon::now('Asia/Dhaka')->format('Y-m-d H:i:s');

                $query->where('is_active', 1)
                    ->where('start_at', '<=', $now)
                    ->where('end_at', '>=', $now);
            });
    }

    public function preOwned()
    {
        return $this->hasOne(PreOwned::class, 'product_id', 'id');
    }

    public function giftProduct()
    {
        return $this->hasOne(GiftProduct::class, 'product_id', 'id');
    }

    public function couponScopes(): HasMany
    {
        return $this->hasMany(CouponProductScope::class, 'product_id', 'id');
    }

    public function coupon(): HasOneThrough
    {
        return $this->hasOneThrough(
            NewCoupon::class,
            CouponProductScope::class,
            'product_id',
            'id',
            'id',
            'new_coupon_id'
        )->where('new_coupons.start_date', '<=', now())
            ->where('new_coupons.end_date', '>=', now())
            ->where('new_coupons.status', 1)
            ->latest('new_coupons.id');
    }

    public function productViews()
    {
        return $this->hasMany(ProductView::class, 'product_id', 'id');
    }

    public function cares()
    {
        return $this->hasMany(ProductCare::class, 'product_id', 'id');
    }

    public function preOwnedSeo()
    {
        return $this->hasOne(PreOwnedProductSeo::class, 'product_id', 'id');
    }
}
