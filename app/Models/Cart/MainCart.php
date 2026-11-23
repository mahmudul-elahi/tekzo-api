<?php

namespace App\Models\Cart;

use App\Models\Product\Product;
use App\Models\Product\ProductVariant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'is_select',
    'customer_id',
    'contact_id',
    'product_id',
    'variant_id',
    'sku',
    'product_name',
    'attributes',
    'featured_image',
    'category_name',
    'brand_logo',
    'max_retail_price',
    'final_price',
    'quantity',
    'headline',
    'discount_type',
    'discount_value',
    'discount_amount',
    'discount_total',
    'booking_amount',
])]
class MainCart extends Model
{
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id', 'id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
