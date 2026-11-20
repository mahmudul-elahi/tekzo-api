<?php

namespace App\Services\Cart;

use App\Models\Cart\Cart;

class CartService
{
    public function recalculate(Cart $cart): void
    {
        $cart->load(['cartItems.productCare.care', 'cartItems.preOwnedCare.care']);

        $subtotal = $cart->cartItems->sum(function ($item) {
            $price = $item->unit_price * $item->quantity;

            if ($item->discount_type === 'percent') {
                $price -= (($price * $item->discount_amount) / 100);
            } elseif ($item->discount_type === 'fixed') {
                $price -= ($item->discount_amount * $item->quantity);
            }

            // care charge applied on price after discount
            $care        = $item->pre_owned_id ? $item->preOwnedCare : $item->productCare;
            $carePercent = $care?->care?->value ?? 0;
            if ($carePercent > 0) {
                $price += ($price * $carePercent) / 100;
            }

            return max($price, 0);
        });


        $totalBookingAmount = $cart->cartItems->sum(
            fn($item) => ($item->booking_amount ?? 0) * $item->quantity
        );

        $couponDiscount = min($cart->coupon_discount ?? 0, $subtotal);

        $cart->update([
            'sub_total' => $subtotal,
            'grand_total' => max($subtotal - $couponDiscount, 0),
            'total_booking_amount' => $totalBookingAmount,
        ]);
    }
}
