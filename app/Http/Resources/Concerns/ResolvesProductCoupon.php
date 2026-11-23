<?php

namespace App\Http\Resources\Concerns;

use App\Repositories\Coupon\CouponRepository;

trait ResolvesProductCoupon
{
    /**
     * @return array{code: string, discount_type: string, amount: float}|null
     */
    private function resolveCoupon($product = null): ?array
    {
        $p = $product ?? $this;

        $coupon = app(CouponRepository::class)->findBestPublicForProduct($p->id, $p->brand_id, $p->category_id);

        return $this->formatResolvedCoupon($coupon);
    }

    /**
     * @return array{code: string, discount_type: string, amount: float}|null
     */
    private function resolvePreOwnedCoupon($preOwned): ?array
    {
        $coupon = app(CouponRepository::class)->findBestPublicForPreOwned($preOwned->id);

        return $this->formatResolvedCoupon($coupon);
    }

    /**
     * @return array{code: string, discount_type: string, amount: float}|null
     */
    private function formatResolvedCoupon(?object $coupon): ?array
    {
        if (! $coupon) {
            return null;
        }

        return [
            'code' => $coupon->code,
            'discount_type' => $coupon->discount_type,
            'amount' => (float) $coupon->discount_value,
        ];
    }
}
