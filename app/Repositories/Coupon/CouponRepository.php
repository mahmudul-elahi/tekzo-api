<?php

namespace App\Repositories\Coupon;

use App\Models\Coupon\CustomerCouponLog;
use App\Models\Coupon\NewCoupon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class CouponRepository
{
    /**
     * The most specific public, currently valid coupon for a product
     * (product scope first, then brand, category, and finally "all").
     *
     * @return object{code: string, discount_type: string, discount_value: mixed}|null
     */
    public function findBestPublicForProduct(int $productId, ?int $brandId, ?int $categoryId): ?object
    {
        return $this->activePublicCoupons()
            ->where(function ($q) use ($productId, $brandId, $categoryId) {
                $q->where(fn ($q) => $q->where('coupon_product_scopes.scope_type', 'product')->where('coupon_product_scopes.product_id', $productId))
                    ->orWhere(fn ($q) => $q->where('coupon_product_scopes.scope_type', 'brand')->where('coupon_product_scopes.brand_id', $brandId))
                    ->orWhere(fn ($q) => $q->where('coupon_product_scopes.scope_type', 'category')->where('coupon_product_scopes.category_id', $categoryId))
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
    }

    /**
     * The most specific public, currently valid coupon for a pre-owned item
     * (pre-owned scope first, then "all").
     *
     * @return object{code: string, discount_type: string, discount_value: mixed}|null
     */
    public function findBestPublicForPreOwned(int $preOwnedId): ?object
    {
        return $this->activePublicCoupons()
            ->where(function ($q) use ($preOwnedId) {
                $q->where(fn ($q) => $q->where('coupon_product_scopes.scope_type', 'pre_owned')->where('coupon_product_scopes.pre_owned_id', $preOwnedId))
                    ->orWhere('coupon_product_scopes.scope_type', 'all');
            })
            ->orderByRaw("CASE
                WHEN coupon_product_scopes.scope_type = 'pre_owned' THEN 1
                WHEN coupon_product_scopes.scope_type = 'all'       THEN 2
                ELSE 3 END ASC")
            ->select('new_coupons.code', 'new_coupons.discount_type', 'new_coupons.discount_value')
            ->first();
    }

    /**
     * An active coupon valid today, with its product scopes and usage count.
     */
    public function findValidByCode(string $code): ?NewCoupon
    {
        return NewCoupon::where('code', $code)
            ->where('status', true)
            ->where(fn ($q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', now()))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', now()))
            ->with(['productScopes'])
            ->withCount('usages')
            ->first();
    }

    public function isUsedByContact(int $couponId, mixed $contactId): bool
    {
        return CustomerCouponLog::where('new_coupon_id', $couponId)
            ->where('contact_id', $contactId)
            ->exists();
    }

    /**
     * Public site-wide coupons valid today, newest first.
     *
     * @return Collection<int, NewCoupon>
     */
    public function publicSiteWide(): Collection
    {
        return NewCoupon::where('is_public', true)
            ->where('type', 'all')
            ->where('status', true)
            ->where(fn ($q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', now()))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', now()))
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
    }

    /**
     * Public coupons that are active today, joined with their product scopes.
     */
    private function activePublicCoupons(): Builder
    {
        return DB::table('new_coupons')
            ->join('coupon_product_scopes', 'coupon_product_scopes.new_coupon_id', '=', 'new_coupons.id')
            ->where('new_coupons.status', 1)
            ->where('new_coupons.is_public', true)
            ->where(fn ($q) => $q->whereNull('new_coupons.start_date')->orWhereDate('new_coupons.start_date', '<=', now()))
            ->where(fn ($q) => $q->whereNull('new_coupons.end_date')->orWhereDate('new_coupons.end_date', '>=', now()));
    }
}
