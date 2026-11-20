<?php

namespace App\Models\ProductVerify;

use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Model;

class ProductVerify extends Model
{
    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
