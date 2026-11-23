<?php

namespace App\Models\Wishlist;

use App\Models\Product\Product;
use App\Models\Product\ProductVariant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'wishlist_id',
    'product_id',
    'product_variant_id',
    'is_select',
    'booking_amount',
    'attributes',
    'unit_price',
    'quantity',
    'discount_type',
    'discount_amount',
])]
class WishlistItem extends Model
{
    protected $casts = [
        'attributes' => 'array',
    ];

    public function wishlist()
    {
        return $this->belongsTo(Wishlist::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
