<?php

namespace App\Models\Gift;

use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'product_id',
    'is_active',
])]
class GiftProduct extends Model
{
    use SoftDeletes;

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(GiftProductVariant::class, 'gift_product_id', 'id');
    }
}
