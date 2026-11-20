<?php

namespace App\Services\Wishlist;

use App\Models\Widget\Widget;
use App\Models\Wishlist\Wishlist;

class WishlistService
{
    public function recalculate(Wishlist $cart): void
    {
        $cart->load('items');

        $subtotal = $cart->items->sum(function ($item) {
            $price = $item->unit_price * $item->quantity;

            if ($item->discount_type === 'percent') {
                $price -= (($price * $item->discount_amount) / 100);
            } elseif ($item->discount_type === 'fixed') {
                $price -= $item->discount_amount;
            }

            return max($price, 0);
        });


        $cart->update([
            'sub_total' => $subtotal,
            'grand_total' => $subtotal,
        ]);
    }
}
