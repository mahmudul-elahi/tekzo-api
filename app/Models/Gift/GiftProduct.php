<?php

namespace App\Models\Gift;

use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GiftProduct extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'product_id',
        'is_active',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(GiftProductVariant::class, 'gift_product_id', 'id');
    }
}
