<?php

namespace App\Models\Cart;

use App\Models\Product\Product;
use App\Models\Product\ProductVariant;
use Illuminate\Database\Eloquent\Model;

class MainCart extends Model
{

    protected $fillable = [
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
    ];

    public function productVariant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id', 'id');
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
