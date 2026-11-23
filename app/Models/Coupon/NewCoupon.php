<?php

namespace App\Models\Coupon;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code',
    'type',
    'discount_type',
    'discount_value',
    'start_date',
    'end_date',
    'usage_limit',
    'min_purchase_amount',
    'status',
    'is_public',
])]
class NewCoupon extends Model
{
    use SoftDeletes;

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => 'boolean',
        'discount_value' => 'float',
        'is_public' => 'boolean',
    ];

    public function productScopes(): HasMany
    {
        return $this->hasMany(CouponProductScope::class, 'new_coupon_id', 'id');
    }

    public function customerScopes(): HasMany
    {
        return $this->hasMany(CouponCustomerScope::class, 'new_coupon_id', 'id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CustomerCouponLog::class, 'new_coupon_id', 'id');
    }
}
