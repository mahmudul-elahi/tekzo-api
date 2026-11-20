<?php

namespace App\Support;

class PriceCalculator
{

    public static function resolveProductDiscount($offerProduct = null)
    {
        $discountType   = $offerProduct?->discount_type;
        $discountAmount = $offerProduct?->discount_amount ?? 0;

        return [
            'percent' => $discountType === 'percentage' ? $discountAmount : 0,
            'fixed'   => $discountType === 'fixed'      ? $discountAmount : 0,
        ];
    }

    public static function finalPrice(
        float $price,
        float $discountPercent = 0,
        float $discountFixed = 0
    ): float {

        if ($discountPercent > 0) {
            return $price - ($price * ($discountPercent / 100));
        }

        if ($discountFixed > 0) {
            return $price - $discountFixed;
        }

        return $price;
    }

    public static function discountAmount(
        float $price,
        float $discountPercent = 0,
        float $discountFixed = 0
    ): float {

        if ($discountPercent > 0) {
            return $price * ($discountPercent / 100);
        }

        if ($discountFixed > 0) {
            return $discountFixed;
        }

        return 0;
    }

    public static function discountLabel(
        float $discountPercent = 0,
        float $discountFixed = 0
    ): ?string {

        if ($discountPercent > 0) {
            return $discountPercent . '% OFF';
        }

        if ($discountFixed > 0) {
            return $discountFixed . ' OFF';
        }

        return null;
    }

    public static function discountSave(
        float $discountPercent = 0,
        float $discountFixed = 0
    ): ?string {

        if ($discountPercent > 0) {
            return $discountPercent . '%';
        }

        if ($discountFixed > 0) {
            return $discountFixed;
        }

        return null;
    }
}
