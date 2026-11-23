<?php

namespace App\Support;

use App\Models\Product\ProductVariant;

/**
 * The discount stored on cart and wishlist items for a variant.
 */
class VariantDiscount
{
    /**
     * An offer with a discount wins over the variant price's own discount;
     * a percent price discount wins over a fixed one.
     *
     * @return array{discount_type: ?string, discount_amount: mixed}
     */
    public static function forVariant(ProductVariant $variant): array
    {
        if ($variant?->offerProduct?->discount_amount > 0) {
            return [
                'discount_type' => $variant->offerProduct->discount_type === 'percentage' ? 'percent' : 'fixed',
                'discount_amount' => $variant->offerProduct->discount_amount,
            ];
        }

        return [
            'discount_type' => $variant->price?->discount_percent > 0 ? 'percent' : ($variant->price?->discount_fixed > 0 ? 'fixed' : null),

            'discount_amount' => $variant->price?->discount_percent > 0
                    ? $variant->price->discount_percent
                    : ($variant->price?->discount_fixed ?? 0),
        ];
    }
}
