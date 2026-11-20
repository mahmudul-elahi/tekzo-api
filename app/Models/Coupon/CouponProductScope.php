<?php

namespace App\Models\Coupon;

use App\Models\Brand\Brand;
use App\Models\Category\Category;
use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CouponProductScope extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'new_coupon_id',
        'scope_type',   // all, category, product, brand
        'category_id',
        'product_id',
        'brand_id',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(NewCoupon::class, 'new_coupon_id', 'id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id', 'id');
    }
}
