<?php

namespace App\Models\Coupon;

use App\Models\Customer\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CouponCustomerScope extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'new_coupon_id',
        'customer_id',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(NewCoupon::class, 'new_coupon_id', 'id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }
}
