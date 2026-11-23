<?php

namespace App\Services\Order;

use App\Enums\OnlinePaymentStatusEnum;
use App\Enums\StatusEnum;
use App\Exceptions\GeneralException;
use App\Models\Order\Order;
use App\Repositories\Address\CustomerAddressRepository;
use App\Repositories\Cart\CartRepository;
use App\Repositories\Coupon\CouponRepository;
use App\Repositories\Order\OrderListRepository;
use App\Repositories\Order\OrderRepository;
use App\Services\Cart\CartService;
use App\Services\Shipping\ShippingChargeService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Turns a signed-in customer's cart into an order.
 */
class CheckoutService
{
    public function __construct(
        private CartRepository $carts,
        private CartService $cartService,
        private CustomerAddressRepository $addresses,
        private OrderRepository $orders,
        private OrderListRepository $orderLists,
        private CouponRepository $coupons,
    ) {}

    /**
     * Recalculate the cart, create the order and its lines, empty the cart, and log the coupon use.
     * Returns null when the cart is empty.
     *
     * @throws GeneralException when the order can't be created
     * @throws Throwable
     */
    public function placeOrder(mixed $customerId, mixed $shippingAddressId, string $paymentMethod, mixed $bookingAmountOnly): ?Order
    {
        $cart = $this->carts->findForCheckout($customerId);

        if (! $cart || $cart->cartItems->isEmpty()) {
            return null;
        }

        $this->cartService->recalculate($cart);
        $cart->refresh()->load(['cartItems.product', 'cartItems.preOwned', 'coupon']);

        $shippingAddress = $this->addresses->findById($shippingAddressId);
        $shippingCharge = ShippingChargeService::resolveForArea(
            (int) $shippingAddress->new_district_id,
            (int) $shippingAddress->area_id
        );
        $grandTotal = $cart->grand_total + $shippingCharge;

        try {
            return DB::transaction(function () use ($customerId, $shippingAddressId, $paymentMethod, $bookingAmountOnly, $cart, $shippingCharge, $grandTotal) {
                $order = $this->orders->create([
                    'contact_id' => $customerId,
                    'shipping_address_id' => $shippingAddressId,
                    'coupon_code' => $cart->coupon?->code,
                    'discount_total' => $cart->coupon_discount,
                    'total_booking_amount' => $bookingAmountOnly ? $cart->total_booking_amount : 0,
                    'sub_total' => $cart->sub_total,
                    'shipping_charge' => $shippingCharge,
                    'due_amount' => $grandTotal,
                    'payable_amount' => 0,
                    'grand_total' => $grandTotal,
                    'payment_method' => $paymentMethod,
                    'payment_status' => OnlinePaymentStatusEnum::Pending->value,
                    'status' => StatusEnum::Pending->value,
                ]);

                foreach ($cart->cartItems as $item) {
                    $unitPrice = (float) ($item->unit_price ?? 0);

                    $this->orderLists->create([
                        'order_id' => $order->id,
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'pre_owned_id' => $item->pre_owned_id,
                        'gift_product_variant_id' => $item->gift_product_variant_id,
                        'product_care_id' => $item->product_care_id,
                        'pre_owned_care_id' => $item->pre_owned_care_id,
                        'product_price' => $unitPrice,
                        'quantity' => $item->quantity,
                        'purchase_point' => $item->pre_owned_id
                            ? ($item->preOwned?->purchase_point ?? 0)
                            : (($item->product?->purchase_point ?? 0) * ($item->quantity ?? 0)),
                        'discount_type' => $item->discount_type,
                        'discount_amount' => $item->discount_amount,
                    ]);
                }

                $this->carts->deleteItems($cart->cartItems->pluck('id'));

                if ($cart->coupon_id) {
                    $this->coupons->logUsage($cart->coupon_id, $customerId);

                    $cart->update([
                        'coupon_id' => null,
                        'coupon_discount' => 0,
                    ]);
                }

                return $order;
            });
        } catch (\Exception $e) {
            if (app()->environment('production')) {
                throw new GeneralException('Checkout failed, please try again later', 500);
            }
            throw new GeneralException($e->getMessage(), 500);
        }
    }
}
