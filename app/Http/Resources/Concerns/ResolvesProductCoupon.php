<?php

namespace App\Http\Resources\Concerns;

use Illuminate\Support\Facades\DB;

trait ResolvesProductCoupon
{
    private function resolveCoupon($product = null): ?array
    {
        $p = $product ?? $this;

        $coupon = DB::table('new_coupons')
            ->join('coupon_product_scopes', 'coupon_product_scopes.new_coupon_id', '=', 'new_coupons.id')
            ->where('new_coupons.status', 1)
            ->where('new_coupons.is_public', true)
            ->where(fn($q) => $q->whereNull('new_coupons.start_date')->orWhereDate('new_coupons.start_date', '<=', now()))
            ->where(fn($q) => $q->whereNull('new_coupons.end_date')->orWhereDate('new_coupons.end_date', '>=', now()))
            ->where(function ($q) use ($p) {
                $q->where(fn($q) => $q->where('coupon_product_scopes.scope_type', 'product')->where('coupon_product_scopes.product_id', $p->id))
                    ->orWhere(fn($q) => $q->where('coupon_product_scopes.scope_type', 'brand')->where('coupon_product_scopes.brand_id', $p->brand_id))
                    ->orWhere(fn($q) => $q->where('coupon_product_scopes.scope_type', 'category')->where('coupon_product_scopes.category_id', $p->category_id))
                    ->orWhere('coupon_product_scopes.scope_type', 'all');
            })
            ->orderByRaw("CASE
                WHEN coupon_product_scopes.scope_type = 'product'  THEN 1
                WHEN coupon_product_scopes.scope_type = 'brand'    THEN 2
                WHEN coupon_product_scopes.scope_type = 'category' THEN 3
                WHEN coupon_product_scopes.scope_type = 'all'      THEN 4
                ELSE 5 END ASC")
            ->select('new_coupons.code', 'new_coupons.discount_type', 'new_coupons.discount_value')
            ->first();

        if (! $coupon) {
            return null;
        }

        return [
            'code'          => $coupon->code,
            'discount_type' => $coupon->discount_type,
            'amount'        => (float) $coupon->discount_value,
        ];
    }

    private function resolvePreOwnedCoupon($preOwned): ?array
    {
        $coupon = DB::table('new_coupons')
            ->join('coupon_product_scopes', 'coupon_product_scopes.new_coupon_id', '=', 'new_coupons.id')
            ->where('new_coupons.status', 1)
            ->where('new_coupons.is_public', true)
            ->where(fn($q) => $q->whereNull('new_coupons.start_date')->orWhereDate('new_coupons.start_date', '<=', now()))
            ->where(fn($q) => $q->whereNull('new_coupons.end_date')->orWhereDate('new_coupons.end_date', '>=', now()))
            ->where(function ($q) use ($preOwned) {
                $q->where(fn($q) => $q->where('coupon_product_scopes.scope_type', 'pre_owned')->where('coupon_product_scopes.pre_owned_id', $preOwned->id))
                    ->orWhere('coupon_product_scopes.scope_type', 'all');
            })
            ->orderByRaw("CASE
                WHEN coupon_product_scopes.scope_type = 'pre_owned' THEN 1
                WHEN coupon_product_scopes.scope_type = 'all'       THEN 2
                ELSE 3 END ASC")
            ->select('new_coupons.code', 'new_coupons.discount_type', 'new_coupons.discount_value')
            ->first();

        if (! $coupon) {
            return null;
        }

        return [
            'code'          => $coupon->code,
            'discount_type' => $coupon->discount_type,
            'amount'        => (float) $coupon->discount_value,
        ];
    }
}
