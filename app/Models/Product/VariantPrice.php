<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VariantPrice extends Model
{
    use SoftDeletes;

    protected $casts = [
        'max_retail_price' => 'float',
        'discount_percent' => 'float',
        'discount_fixed' => 'float',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('priceHighFirst', function ($query) {
            $query->orderByRaw('max_retail_price > 0 DESC')
                ->orderBy('max_retail_price', 'DESC');
        });
    }
}
