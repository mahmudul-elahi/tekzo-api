<?php

namespace App\Models\Coupon;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCouponLog extends Model
{
    protected $fillable = [
        'new_coupon_id',
        'contact_id',
        'discount_amount',
        'used_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(NewCoupon::class, 'new_coupon_id', 'id');
    }
}
