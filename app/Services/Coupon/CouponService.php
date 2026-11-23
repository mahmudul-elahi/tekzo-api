<?php

namespace App\Services\Coupon;

use App\Exceptions\GeneralException;
use App\Models\Coupon\NewCoupon;
use App\Repositories\Cart\CartRepository;
use App\Repositories\Coupon\CouponRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class CouponService
{
    public function __construct(
        private CouponRepository $coupons,
        private CartRepository $carts,
    ) {}

    /**
     * Apply a coupon to the customer's cart. The discount covers only the items the coupon's
     * scopes match (every item when it has none) and never exceeds the cart sub total.
     *
     * @return array{coupon_code: string, discount_type: string, discount_value: mixed, discount_amount: float, sub_total: mixed, grand_total: mixed}
     *
     * @throws GeneralException when the cart is empty or the coupon is invalid, used up, already used, or not applicable
     */
    public function applyToCart(mixed $contactId, string $code): array
    {
        $cart = $this->carts->findWithItemProducts($contactId);

        if (! $cart || $cart->cartItems->isEmpty()) {
            throw new GeneralException('Cart is empty', 400);
        }

        $coupon = $this->coupons->findValidByCode($code);

        if (! $coupon) {
            throw new GeneralException('Invalid your coupon', 400);
        }

        if ($coupon->usage_limit && $coupon->usages_count >= $coupon->usage_limit) {
            throw new GeneralException('Coupon usage limit exceeded', 400);
        }

        if ($this->coupons->isUsedByContact($coupon->id, $contactId)) {
            throw new GeneralException('You have already used this coupon', 400);
        }

        if ($coupon->min_purchase_amount && $cart->sub_total < $coupon->min_purchase_amount) {
            throw new GeneralException(
                'Minimum purchase amount of '.$coupon->min_purchase_amount.' required',
                400
            );
        }

        $applicableSubTotal = $this->applicableSubTotal($cart->cartItems, $coupon);

        if ($applicableSubTotal <= 0) {
            throw new GeneralException('Coupon is not applicable to any items in your cart', 400);
        }

        $discount = $coupon->discount_type === 'percent'
            ? ($applicableSubTotal * $coupon->discount_value / 100)
            : $coupon->discount_value;

        $discount = min(round($discount, 2), $cart->sub_total);

        DB::transaction(function () use ($cart, $coupon, $discount) {
            $cart->update([
                'coupon_id' => $coupon->id,
                'coupon_discount' => $discount,
                'grand_total' => max($cart->sub_total - $discount, 0),
            ]);
        });

        return [
            'coupon_code' => $coupon->code,
            'discount_type' => $coupon->discount_type,
            'discount_value' => $coupon->discount_value,
            'discount_amount' => $discount,
            'sub_total' => $cart->sub_total,
            'grand_total' => max($cart->sub_total - $discount, 0),
        ];
    }

    /**
     * Remove the coupon from the customer's cart and return the restored grand total.
     *
     * @throws GeneralException when the customer has no cart
     */
    public function removeFromCart(mixed $contactId): mixed
    {
        $cart = $this->carts->findForContact($contactId);

        if (! $cart) {
            throw new GeneralException('Cart not found', 404);
        }

        DB::transaction(function () use ($cart) {
            $cart->update([
                'coupon_id' => null,
                'coupon_discount' => 0,
                'grand_total' => $cart->sub_total,
            ]);
        });

        return $cart->sub_total;
    }

    /**
     * @return Collection<int, NewCoupon>
     */
    public function publicSiteWide(): Collection
    {
        return $this->coupons->publicSiteWide();
    }

    private function applicableSubTotal(SupportCollection $cartItems, NewCoupon $coupon): float
    {
        if ($coupon->productScopes->isEmpty()) {
            return $this->sumItemsTotal($cartItems);
        }

        $applicableItems = collect();

        foreach ($coupon->productScopes as $scope) {
            $matched = match ($scope->scope_type) {
                'all' => $cartItems,
                'product' => $cartItems->filter(fn ($i) => $i->product_id == $scope->product_id),
                'category' => $cartItems->filter(fn ($i) => $i->product?->category_id == $scope->category_id),
                'brand' => $cartItems->filter(fn ($i) => $i->product?->brand_id == $scope->brand_id),
                'pre_owned' => $cartItems->filter(fn ($i) => $i->pre_owned_id && $i->pre_owned_id == $scope->pre_owned_id),
                default => collect(),
            };

            $applicableItems = $applicableItems->merge($matched);
        }

        return $this->sumItemsTotal($applicableItems->unique('id'));
    }

    /**
     * Item totals after their own discounts (pre-owned items at their unit price).
     */
    private function sumItemsTotal(SupportCollection $items): float
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
