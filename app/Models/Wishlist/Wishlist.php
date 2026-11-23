<?php

namespace App\Models\Wishlist;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'contact_id',
    'tenant_id',
    'sub_total',
    'grand_total',
])]
class Wishlist extends Model
{
    public function items()
    {
        return $this->hasMany(WishlistItem::class);
    }
}
