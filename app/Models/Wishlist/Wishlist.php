<?php

namespace App\Models\Wishlist;

use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    protected $fillable = [
        'contact_id',
        'tenant_id',
        'sub_total',
        'grand_total',
    ];

    public function items()
    {
        return $this->hasMany(WishlistItem::class);
    }
}
