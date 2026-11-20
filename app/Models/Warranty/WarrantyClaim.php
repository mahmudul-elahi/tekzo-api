<?php

namespace App\Models\Warranty;

use App\Models\Product\Product;
use App\Models\Product\ProductVariant;
use App\Models\Sale\Sale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarrantyClaim extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'serial_number',
        'product_id',
        'product_variant_id',
        'sale_id',
        'reason',
        'warranty_end_date',
        'is_within_warranty',
        'status',
    ];

    protected $casts = [
        'warranty_end_date' => 'date',
        'is_within_warranty' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id', 'id');
    }
}
