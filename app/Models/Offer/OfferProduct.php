<?php

namespace App\Models\Offer;

use App\Models\Product\Product;
use App\Models\Product\ProductVariant;
use Illuminate\Database\Eloquent\Model;

class OfferProduct extends Model
{

    protected static function booted(): void
    {
        static::addGlobalScope('offerFirst', function ($query) {
            $query->orderByRaw('
                CASE 
                    WHEN product_variant_id IS NOT NULL 
                    AND discount_amount > 0 
                    THEN 0 
                    ELSE 1 
                END ASC
            ');
        });
    }
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }

    public function offer()
    {
        return $this->belongsTo(Offer::class, 'offer_id', 'id');
    }
}
