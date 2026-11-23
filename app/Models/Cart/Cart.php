<?php

namespace App\Models\Cart;

use App\Models\Contact\Contact;
use App\Models\Coupon\NewCoupon;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'contact_id',
    'tenant_id',
    'total_booking_amount',
    'sub_total',
    'grand_total',
    'coupon_id',
    'coupon_discount',
])]
class Cart extends Model
{
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(NewCoupon::class, 'coupon_id');
    }
}
