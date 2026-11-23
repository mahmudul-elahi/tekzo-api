<?php

namespace App\Models\PreOwned;

use App\Models\Care\PreOwnedCare;
use App\Models\Gift\GiftProduct;
use App\Models\Product\Product;
use App\Models\Product\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PreOwned extends Model
{
    use SoftDeletes;

    public $casts = [
        'price' => 'float',
        'booking_amount' => 'float',
        'purchase_point' => 'float',
        'is_sold' => 'boolean',
        'box' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function attributes()
    {
        return $this->hasMany(PreOwnedAttribute::class, 'pre_owned_id', 'id');
    }

    public function images()
    {
        return $this->hasMany(PreOwnedImage::class, 'pre_owned_id', 'id');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }

    public function cares()
    {
        return $this->hasMany(PreOwnedCare::class, 'pre_owned_id', 'id');
    }

    public function gift()
    {
        return $this->hasOne(GiftProduct::class, 'pre_owned_id', 'id');
    }
}
