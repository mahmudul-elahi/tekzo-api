<?php

namespace App\Models\Product;

use App\Models\Gift\GiftProduct;
use App\Models\Offer\OfferProduct;
use App\Models\PreOwned\PreOwned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $casts = [
        'stock' => 'boolean',
        'is_prebook' => 'boolean',
    ];

    public function price()
    {
        return $this->hasOne(VariantPrice::class, 'product_variant_id', 'id');
    }

    public function attributeValues(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'variant_attribute_values', 'product_variant_id')->withTimestamps();
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function offerProduct()
    {
        return $this->hasOne(OfferProduct::class, 'product_variant_id', 'id')
            ->whereHas('offer', function ($query) {
                $query->where('is_active', 1)
                    ->where('start_at', '<=', now())
                    ->where('end_at', '>=', now());
            });
    }

    public function preowned()
    {
        return $this->hasOne(PreOwned::class, 'product_variant_id', 'id');
    }

    public function giftProduct()
    {
        return $this->hasOne(GiftProduct::class, 'product_variant_id', 'id');
    }

    public function scopeOrderByLowestPrice($query): void
    {
        // Some variants have a placeholder max_retail_price of 0 (bad/incomplete data).
        // Push those last so a product with any real-priced variant never displays ৳0 —
        // only falls back to a 0-priced variant if literally none of them have a real price.
        $query->orderByRaw('(
            SELECT CASE
                WHEN vp.discount_percent > 0 THEN vp.max_retail_price - (vp.max_retail_price * vp.discount_percent / 100)
                WHEN vp.discount_fixed > 0   THEN vp.max_retail_price - vp.discount_fixed
                ELSE vp.max_retail_price
            END <= 0
            FROM variant_prices vp
            WHERE vp.product_variant_id = product_variants.id
            LIMIT 1
        ) ASC')->orderByRaw('(
            SELECT CASE
                WHEN vp.discount_percent > 0 THEN vp.max_retail_price - (vp.max_retail_price * vp.discount_percent / 100)
                WHEN vp.discount_fixed > 0   THEN vp.max_retail_price - vp.discount_fixed
                ELSE vp.max_retail_price
            END
            FROM variant_prices vp
            WHERE vp.product_variant_id = product_variants.id
            LIMIT 1
        ) ASC');
    }
}
