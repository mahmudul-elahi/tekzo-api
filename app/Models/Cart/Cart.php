<?php

namespace App\Models\Cart;

use App\Models\Contact\Contact;
use App\Models\Coupon\NewCoupon;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = [
        'contact_id',
        'tenant_id',
        'total_booking_amount',
        'sub_total',
        'grand_total',
        'coupon_id',
        'coupon_discount',
    ];


    public function contact(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function cartItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function coupon(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NewCoupon::class, 'coupon_id');
    }
}
