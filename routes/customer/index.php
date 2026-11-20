<?php

use App\Http\Controllers\BuyNow\BuyNowController;
use App\Http\Controllers\Cart\CartController;
use App\Http\Controllers\Cart\Coupon\CouponController;
use App\Http\Controllers\Checkout\CheckoutController;
use App\Http\Controllers\Customer\Address\ShippingAddressController;
use App\Http\Controllers\Customer\Profile\AddressController;
use App\Http\Controllers\Customer\Profile\ProfileController;
use App\Http\Controllers\Customer\Review\CustomerReviewController;
use App\Http\Controllers\Gust\GustPhoneController;
use App\Http\Controllers\Invoice\InvoiceDownoloadController;
use App\Http\Controllers\Order\OrderController;
use App\Http\Controllers\SellDeviceRequest\SellDeviceRequestController;
use App\Http\Controllers\Warranty\WarrantyClaimController;
use App\Http\Controllers\Wishlist\WishlistController;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1/customer', 'as' => 'customer.', 'middleware' => ['auth:sanctum']], function (Router $router) {

    $router->get('profile', [ProfileController::class, 'index'])
        ->name('profile.index');

    $router->post('profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    $router->post('change-password', [ProfileController::class, 'password'])
        ->name('profile.password');

    $router->post('logout', [ProfileController::class, 'logout'])
        ->name('profile.logout');

    $router->post('email-verify-request', [ProfileController::class, 'emailVerifyRequest'])
        ->name('profile.email-verify-request')
        ->middleware('throttle:otp-send');

    $router->post('email-verify', [ProfileController::class, 'emailVerify'])
        ->name('profile.email-verify')
        ->middleware('throttle:otp-verify');

    $router->post('phone-verify-request', [ProfileController::class, 'phoneVerifyRequest'])
        ->name('profile.phone-verify-request')
        ->middleware('throttle:otp-send');

    $router->post('phone-verify', [ProfileController::class, 'phoneVerify'])
        ->name('profile.phone-verify')
        ->middleware('throttle:otp-verify');

    $router->get('shipping-address', [ShippingAddressController::class, 'index']);
    $router->apiResource('addresses', AddressController::class);
    $router->patch('default-address/{address}', [AddressController::class, 'defaultAddress']);
    $router->get('default-address', [AddressController::class, 'defaultShippingBillingAddress']);

    $router->get('orders', [OrderController::class, 'index']);
    $router->get('orders/{id}/detail', [OrderController::class, 'orderDetail']);
    $router->get('orders/{id}/payment-history', [OrderController::class, 'paymentHistory']);
    $router->get('recent-orders', [OrderController::class, 'recentOrder']);
    $router->get('statistics', [OrderController::class, 'statistics']);

    $router->apiResource('carts', CartController::class)->except(['show', 'destroy']);
    $router->delete('carts/{id}', [CartController::class, 'destroy']);
    $router->patch('carts/item/select-toggle', [CartController::class, 'toggleSelect']);

    $router->apiResource('wishlists', WishlistController::class)
        ->except(['show', 'destroy']);

    $router->delete('wishlists/{id}', [WishlistController::class, 'destroy']);
    $router->patch('wishlists/item/select-toggle', [WishlistController::class, 'toggleSelect']);

    $router->post('apply-coupon', [CouponController::class, 'applyCoupon']);
    $router->delete('remove-coupon', [CouponController::class, 'removeCoupon']);

    $router->post('sell-device-requests/upload-image', [SellDeviceRequestController::class, 'uploadImage']);
    $router->apiResource('sell-device-requests', SellDeviceRequestController::class);

    $router->post('checkouts', [CheckoutController::class, 'checkout']);
    $router->get('delivered-products', [CustomerReviewController::class, 'deliveredProduct']);
    $router->get('delivered-products/{orderListId}', [CustomerReviewController::class, 'deliveredProductDetail']);
    $router->get('reviews', [CustomerReviewController::class, 'index']);
    $router->post('reviews', [CustomerReviewController::class, 'store']);
    $router->get('orders/{order}/product', [CustomerReviewController::class, 'orderProduct']);
    $router->post('orders/{orderId}/repay', [OrderController::class, 'repay'])->name('orders.repay');
});

Route::group(['prefix' => 'v1/customer', 'as' => 'customer.'], function (Router $router) {

    $router->post('gust-check-coupon', [BuyNowController::class, 'checkCoupon']);
    $router->post('gust-buy-now', [BuyNowController::class, 'gustBuyNow'])->middleware('throttle:public-write');
    $router->post('success', [BuyNowController::class, 'success']);
    $router->post('cancel', [BuyNowController::class, 'cancel']);
    $router->post('fail', [BuyNowController::class, 'failed']);

    $router->get('bkash-callback', [BuyNowController::class, 'bkashCallback']);
    $router->get('invoice-download', [InvoiceDownoloadController::class, 'invoiceDownload'])->middleware('throttle:invoice-tracking');
    $router->get('recent-order', [InvoiceDownoloadController::class, 'recentOrder'])->middleware('throttle:invoice-tracking');

    $router->post('gust-phone-verify-request', [GustPhoneController::class, 'phoneVerifyRequest'])
        ->name('profile.gust-phone-verify-request')
        ->middleware('throttle:otp-send');

    $router->post('gust-phone-verify', [GustPhoneController::class, 'phoneVerify'])
        ->name('profile.gust-phone-verify')
        ->middleware('throttle:otp-verify');

    $router->post('warranty-claims', [WarrantyClaimController::class, 'store']);
});
