<?php

namespace App\Models\Care;

use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCare extends Model
{
    use SoftDeletes;

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function care()
    {
        return $this->belongsTo(Care::class, 'care_id', 'id');
    }
}
