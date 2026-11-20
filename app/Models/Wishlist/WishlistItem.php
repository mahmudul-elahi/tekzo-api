<?php

namespace App\Models\Wishlist;

use Illuminate\Database\Eloquent\Model;

class WishlistItem extends Model
{
    protected $fillable = [
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
    ];

    protected $casts = [
        'attributes' => 'array',
    ];

    public function wishlist()
    {
        return $this->belongsTo(Wishlist::class);
    }

    public function product()
    {
        return $this->belongsTo(\App\Models\Product\Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(\App\Models\Product\ProductVariant::class, 'product_variant_id');
    }
}
