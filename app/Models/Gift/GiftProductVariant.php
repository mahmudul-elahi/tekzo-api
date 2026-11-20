<?php

namespace App\Models\Gift;

use App\Models\Product\Product;
use App\Models\Product\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GiftProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'gift_product_id',
        'product_id',
        'product_variant_id',
        'qty',
    ];

    public function giftProduct(): BelongsTo
    {
        return $this->belongsTo(GiftProduct::class, 'gift_product_id', 'id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }
}
