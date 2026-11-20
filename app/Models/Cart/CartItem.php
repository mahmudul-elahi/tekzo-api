<?php

namespace App\Models\Cart;

use App\Models\Care\PreOwnedCare;
use App\Models\Care\ProductCare;
use App\Models\Gift\GiftProductVariant;
use App\Models\PreOwned\PreOwned;
use App\Models\Product\Product;
use App\Models\Product\ProductVariant;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    public $fillable = [
        'is_select',
        'cart_id',
        'product_id',
        'product_variant_id',
        'pre_owned_id',
        'gift_product_variant_id',
        'product_care_id',
        'pre_owned_care_id',
        'attributes',
        'booking_amount',
        'unit_price',
        'quantity',
        'discount_type',
        'discount_amount',
    ];

    public $casts = [
        'is_select' => 'boolean'
    ];

    public function cart(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function preOwned()
    {
        return $this->belongsTo(PreOwned::class, 'pre_owned_id', 'id');
    }

    public function giftProductVariant()
    {
        return $this->belongsTo(GiftProductVariant::class, 'gift_product_variant_id', 'id');
    }

    public function productCare()
    {
        return $this->belongsTo(ProductCare::class, 'product_care_id', 'id');
    }

    public function preOwnedCare()
    {
        return $this->belongsTo(PreOwnedCare::class, 'pre_owned_care_id', 'id');
    }
}
