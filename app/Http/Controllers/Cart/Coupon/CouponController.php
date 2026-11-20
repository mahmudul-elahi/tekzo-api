<?php

namespace App\Http\Controllers\Cart\Coupon;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Models\Cart\Cart;
use App\Models\Coupon\CustomerCouponLog;
use App\Models\Coupon\NewCoupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CouponController extends Controller
{
    public function applyCoupon(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $customerId = auth()->guard('customer')->id();

        $cart = Cart::with([
            'cartItems.product.category',
            'cartItems.product.brand',
        ])
            ->where('contact_id', $customerId)
            ->first();

        if (!$cart || $cart->cartItems->isEmpty()) {
            throw new GeneralException('Cart is empty', 400);
        }

        $coupon = NewCoupon::where('code', $request->code)
            ->where('status', true)
            ->where(fn($q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', now()))
            ->where(fn($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', now()))
            ->with(['productScopes'])
            ->withCount('usages')
            ->first();

        if (!$coupon) {
            throw new GeneralException('Invalid your coupon', 400);
        }

        if ($coupon->usage_limit && $coupon->usages_count >= $coupon->usage_limit) {
            throw new GeneralException('Coupon usage limit exceeded', 400);
        }

        $alreadyUsed = CustomerCouponLog::where('new_coupon_id', $coupon->id)
            ->where('contact_id', $customerId)
            ->exists();

        if ($alreadyUsed) {
            throw new GeneralException('You have already used this coupon', 400);
        }

        if ($coupon->min_purchase_amount && $cart->sub_total < $coupon->min_purchase_amount) {
            throw new GeneralException(
                'Minimum purchase amount of ' . $coupon->min_purchase_amount . ' required',
                400
            );
        }

        $applicableSubTotal = $this->getApplicableSubTotal($cart->cartItems, $coupon);

        if ($applicableSubTotal <= 0) {
            throw new GeneralException('Coupon is not applicable to any items in your cart', 400);
        }

        $discount = $coupon->discount_type === 'percent'
            ? ($applicableSubTotal * $coupon->discount_value / 100)
            : $coupon->discount_value;

        $discount = min(round($discount, 2), $cart->sub_total);

        DB::transaction(function () use ($cart, $coupon, $discount) {
            $cart->update([
                'coupon_id'       => $coupon->id,
                'coupon_discount' => $discount,
                'grand_total'     => max($cart->sub_total - $discount, 0),
            ]);
        });

        return successResponse([
            'coupon_code'     => $coupon->code,
            'discount_type'   => $coupon->discount_type,
            'discount_value'  => $coupon->discount_value,
            'discount_amount' => $discount,
            'sub_total'       => $cart->sub_total,
            'grand_total'     => max($cart->sub_total - $discount, 0),
        ]);
    }

    public function publicCoupons()
    {
        $coupons = NewCoupon::where('is_public', true)
            ->where('type', 'all')
            ->where('status', true)
            ->where(fn($q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', now()))
            ->where(fn($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', now()))
            ->orderBy('id', 'desc')
            ->get([
                'id',
                'code',
                'type',
                'discount_type',
                'discount_value',
                'start_date',
                'end_date',
                'usage_limit',
                'min_purchase_amount',
            ]);

        return successResponse($coupons);
    }

    public function removeCoupon()
    {
        $customerId = auth()->guard('customer')->id();

        $cart = Cart::where('contact_id', $customerId)->first();

        if (!$cart) {
            throw new GeneralException('Cart not found', 404);
        }

        DB::transaction(function () use ($cart) {
            $cart->update([
                'coupon_id'       => null,
                'coupon_discount' => 0,
                'grand_total'     => $cart->sub_total,
            ]);
        });

        return successResponse([
            'grand_total' => $cart->sub_total,
        ]);
    }

    private function getApplicableSubTotal($cartItems, NewCoupon $coupon): float
    {
        if ($coupon->productScopes->isEmpty()) {
            return $this->sumItemsTotal($cartItems);
        }

        $applicableItems = collect();

        foreach ($coupon->productScopes as $scope) {
            $matched = match ($scope->scope_type) {
                'all'       => $cartItems,
                'product'   => $cartItems->filter(fn($i) => $i->product_id == $scope->product_id),
                'category'  => $cartItems->filter(fn($i) => $i->product?->category_id == $scope->category_id),
                'brand'     => $cartItems->filter(fn($i) => $i->product?->brand_id == $scope->brand_id),
                'pre_owned' => $cartItems->filter(fn($i) => $i->pre_owned_id && $i->pre_owned_id == $scope->pre_owned_id),
                default     => collect(),
            };

            $applicableItems = $applicableItems->merge($matched);
        }

        return $this->sumItemsTotal($applicableItems->unique('id'));
    }

    private function sumItemsTotal($items): float
    {
        return $items->sum(function ($item) {
            if ($item->pre_owned_id) {
                return max($item->unit_price * $item->quantity, 0);
            }

            $price = $item->unit_price * $item->quantity;

            if ($item->discount_type === 'percent') {
                $price -= ($price * $item->discount_amount / 100);
            } elseif ($item->discount_type === 'fixed') {
                $price -= $item->discount_amount * $item->quantity;
            }

            return max($price, 0);
        });
    }
}
