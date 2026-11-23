<?php

namespace App\Services\Coupon;

use App\Exceptions\GeneralException;
use App\Models\Cart\Cart;
use App\Models\Coupon\NewCoupon;
use App\Repositories\Cart\CartRepository;
use App\Repositories\Coupon\CouponRepository;
use App\Repositories\Product\ProductRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class CouponService
{
    public function __construct(
        private readonly CouponRepository $coupons,
        private readonly CartRepository $carts,
        private readonly ProductRepository $products,
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

        if (! $cart instanceof Cart || $cart->cartItems->isEmpty()) {
            throw new GeneralException('Cart is empty', 400);
        }

        $coupon = $this->coupons->findValidByCode($code);

        if (! $coupon instanceof NewCoupon) {
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

        $discount = $this->isPercentage($coupon)
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

        if (! $cart instanceof Cart) {
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

    /**
     * Validate a coupon for guest order lines and work out its discount (capped at the grand total).
     * Guests have no usage log check, unlike cart coupons.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{0: NewCoupon, 1: string, 2: float}
     *
     * @throws GeneralException when the coupon is invalid, used up, below the minimum, or not applicable
     */
    public function discountForGuestLines(mixed $code, array $lines, float $grandTotal): array
    {
        $coupon = $this->coupons->findValidByCode($code);

        if (! $coupon instanceof NewCoupon) {
            throw new GeneralException('Invalid your coupon', 400);
        }

        if ($coupon->usage_limit && $coupon->usages_count >= $coupon->usage_limit) {
            throw new GeneralException('Coupon usage limit exceeded', 400);
        }

        if ($coupon->min_purchase_amount && $grandTotal < $coupon->min_purchase_amount) {
            throw new GeneralException(
                'Minimum purchase amount of '.$coupon->min_purchase_amount.' required',
                400
            );
        }

        $applicableTotal = $this->guestApplicableTotal($lines, $coupon);

        if ($applicableTotal <= 0) {
            throw new GeneralException('Coupon is not applicable to any items in your order', 400);
        }

        $discount = $this->isPercentage($coupon)
            ? ($applicableTotal * $coupon->discount_value / 100)
            : $coupon->discount_value;

        return [$coupon, $coupon->code, min(round($discount, 2), $grandTotal)];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function guestApplicableTotal(array $lines, NewCoupon $coupon): float
    {
        $productIds = array_unique(array_column($lines, 'product_id'));
        $preOwnedIds = array_values(array_filter(array_column($lines, 'pre_owned_id')));

        if ($coupon->productScopes->isEmpty()) {
            return $this->sumGuestLines($lines, $productIds, $preOwnedIds);
        }

        $applicableProductIds = [];
        $applicablePreOwnedIds = [];

        $products = $this->products->withBrandAndCategoryKeyedById($productIds);

        foreach ($coupon->productScopes as $scope) {
            if ($scope->scope_type === 'pre_owned' && $scope->pre_owned_id) {
                if (in_array($scope->pre_owned_id, $preOwnedIds)) {
                    $applicablePreOwnedIds[] = $scope->pre_owned_id;
                }

                continue;
            }

            $matched = match ($scope->scope_type) {
                'all' => $productIds,
                'product' => array_filter($productIds, fn ($id) => $id == $scope->product_id),
                'category' => array_filter($productIds, fn ($id) => $products[$id]?->category_id == $scope->category_id),
                'brand' => array_filter($productIds, fn ($id) => $products[$id]?->brand_id == $scope->brand_id),
                default => [],
            };

            $applicableProductIds = array_unique(array_merge($applicableProductIds, array_values($matched)));
        }

        return $this->sumGuestLines($lines, $applicableProductIds, $applicablePreOwnedIds);
    }

    /**
     * Totals of the matching lines after their own discounts; pre-owned lines count only when their unit matched.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<int, mixed>  $productIds
     * @param  array<int, mixed>  $preOwnedIds
     */
    private function sumGuestLines(array $lines, array $productIds, array $preOwnedIds = []): float
    {
        return array_sum(array_map(function ($item) use ($productIds, $preOwnedIds) {
            $itemPreOwnedId = $item['pre_owned_id'] ?? null;

            if ($itemPreOwnedId && in_array($itemPreOwnedId, $preOwnedIds)) {
                return max($item['product_price'] * $item['quantity'], 0);
            }

            if (! $itemPreOwnedId && ! in_array($item['product_id'], $productIds)) {
                return 0;
            }

            if ($itemPreOwnedId) {
                return 0;
            }

            $price = $item['product_price'] * $item['quantity'];

            if (($item['discount_type'] ?? null) === 'percent') {
                $price -= ($price * $item['discount_amount'] / 100);
            } elseif (($item['discount_type'] ?? null) === 'fixed') {
                $price -= $item['discount_amount'] * $item['quantity'];
            }

            return max($price, 0);
        }, $lines));
    }

    /**
     * Coupons are stored as 'percentage' (older data may say 'percent').
     */
    private function isPercentage(NewCoupon $coupon): bool
    {
        return in_array($coupon->discount_type, ['percentage', 'percent'], true);
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
