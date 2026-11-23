<?php

namespace App\Http\Controllers\Api\V1\Cart\Coupon;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\ApplyCouponRequest;
use App\Services\Coupon\CouponService;
use Illuminate\Http\JsonResponse;

class CouponController extends Controller
{
    public function __construct(private readonly CouponService $couponService) {}

    /**
     * @throws GeneralException
     */
    public function applyCoupon(ApplyCouponRequest $request): JsonResponse
    {

        return successResponse($this->couponService->applyToCart(auth()->guard('customer')->id(), $request->code));
    }

    /**
     * Not routed at the moment.
     */
    public function publicCoupons(): JsonResponse
    {
        return successResponse($this->couponService->publicSiteWide());
    }

    /**
     * @throws GeneralException
     */
    public function removeCoupon(): JsonResponse
    {
        return successResponse([
            'grand_total' => $this->couponService->removeFromCart(auth()->guard('customer')->id()),
        ]);
    }
}
