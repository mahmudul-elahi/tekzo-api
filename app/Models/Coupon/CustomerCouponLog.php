<?php

namespace App\Models\Coupon;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'new_coupon_id',
    'contact_id',
    'discount_amount',
    'used_at',
])]
class CustomerCouponLog extends Model
{
    protected $casts = [
        'used_at' => 'datetime',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(NewCoupon::class, 'new_coupon_id', 'id');
    }
}
