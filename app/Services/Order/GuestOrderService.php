<?php

namespace App\Services\Order;

use App\Enums\StatusEnum;
use App\Exceptions\GeneralException;
use App\Models\Contact\Contact;
use App\Models\Order\Order;
use App\Repositories\Care\CareRepository;
use App\Repositories\Coupon\CouponRepository;
use App\Repositories\Customer\ContactRepository;
use App\Repositories\Order\OrderListRepository;
use App\Repositories\Order\OrderRepository;
use App\Repositories\PreOwned\PreOwnedRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductVariantRepository;
use App\Services\Coupon\CouponService;
use App\Services\Shipping\ShippingChargeService;
use App\Support\VariantDiscount;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * "Buy now" orders placed by guests, priced from the submitted cart lines.
 */
class GuestOrderService
{
    public function __construct(
        private ProductRepository $products,
        private ProductVariantRepository $variants,
        private PreOwnedRepository $preOwneds,
        private CareRepository $cares,
        private ContactRepository $contacts,
        private OrderRepository $orders,
        private OrderListRepository $orderLists,
        private CouponRepository $coupons,
        private CouponService $couponService,
    ) {}

    /**
     * Price the lines, apply an optional coupon, find or create the guest contact, and create the order.
     *
     * @param  array<string, mixed>  $input  validated request data
     *
     * @throws GeneralException any failure, re-thrown with status 500
     * @throws Throwable
     */
    public function placeOrder(array $input): Order
    {
        try {
            return DB::transaction(function () use ($input) {
                [$cartItems, $subTotal, $discountTotal, $totalBookingAmount] = $this->priceLines($input['carts'], (bool) ($input['booking_amount_only'] ?? null));

                $shippingCharge = ShippingChargeService::resolveForArea((int) $input['district_id'], (int) $input['area_id']);
                $grandTotal = ($subTotal - $discountTotal) + $shippingCharge;

                $couponCode = null;
                $couponDiscount = 0;
                $appliedCoupon = null;

                if (filled($input['coupon_code'] ?? null)) {
                    [$appliedCoupon, $couponCode, $couponDiscount] = $this->couponService->discountForGuestLines($input['coupon_code'], $cartItems, $grandTotal);
                    $grandTotal = max($grandTotal - $couponDiscount, 0);
                }

                $contact = $this->findOrCreateGuest($input);

                $order = $this->orders->create([
                    'contact_id' => $contact->id,
                    'payment_method' => $input['payment_method'],
                    'payment_status' => null,
                    'new_district_id' => $input['district_id'],
                    'area_id' => $input['area_id'],
                    'address' => $input['address'],
                    'total_booking_amount' => (float) $totalBookingAmount,
                    'sub_total' => (float) $subTotal,
                    'shipping_charge' => (float) $shippingCharge,
                    'coupon_code' => $couponCode,
                    'discount_total' => (float) $couponDiscount,
                    'grand_total' => (float) $grandTotal,
                    'due_amount' => (float) $grandTotal,
                    'payable_amount' => 0,
                    'status' => StatusEnum::Pending->value,
                    'customer_note' => $input['note'] ?? null,
                    'custom_value' => json_encode([
                        'customer_name' => $input['full_name'],
                    ]),
                ]);

                $this->orderLists->insert(array_map(fn ($item) => array_merge($item, ['order_id' => $order->id]), $cartItems));

                if ($appliedCoupon) {
                    $this->coupons->logUsage($appliedCoupon->id, $contact->id);
                }

                return $order;
            });
        } catch (\Exception $e) {
            throw new GeneralException(
                app()->isProduction()
                    ? 'Failed to place order. Please try again later.'
                    : $e->getMessage(),
                500
            );
        }
    }

    /**
     * Price the lines without booking amounts and quote a coupon for them.
     *
     * @param  array<int, array<string, mixed>>  $carts
     * @return array{coupon_code: string, sub_total: float, discount_amount: float, grand_total: float}
     *
     * @throws GeneralException when a line can't be priced or the coupon doesn't apply
     */
    public function quoteCoupon(mixed $code, array $carts): array
    {
        [$cartItems, $subTotal, $discountTotal] = $this->priceLines($carts, false);

        $grandTotal = $subTotal - $discountTotal;

        [, $couponCode, $couponDiscount] = $this->couponService->discountForGuestLines($code, $cartItems, $grandTotal);

        return [
            'coupon_code' => $couponCode,
            'sub_total' => $grandTotal,
            'discount_amount' => $couponDiscount,
            'grand_total' => max($grandTotal - $couponDiscount, 0),
        ];
    }

    /**
     * Build order line rows and the totals. Pre-owned lines use the unit's own price and
     * discount (quantity 1); product lines use the variant price and VariantDiscount.
     * Care charges are added to the sub total; booking amounts only when requested.
     *
     * @param  array<int, array<string, mixed>>  $carts
     * @return array{0: array<int, array<string, mixed>>, 1: float|int, 2: float|int, 3: float|int}
     *
     * @throws GeneralException when a line is sold, out of stock, or missing
     */
    private function priceLines(array $carts, bool $bookingAmount): array
    {
        $subTotal = 0;
        $discountTotal = 0;
        $cartItems = [];
        $totalBookingAmount = 0;

        foreach ($carts as $cart) {
            $quantity = $cart['quantity'];
            $usedDevice = null;

            if (isset($cart['pre_owned_id']) && ! empty($cart['pre_owned_id'])) {
                if ($quantity > 1) {
                    throw new GeneralException('Pre-owned device quantity must be 1', 400);
                }

                $usedDevice = $this->preOwneds->findUnsoldForUpdate($cart['pre_owned_id'], $cart['product_variant_id']);

                if (! $usedDevice) {
                    throw new GeneralException('This device is already sold', 400);
                }
            }

            if ($usedDevice) {
                $regularPrice = (float) $usedDevice->price;

                $preOwnedDiscountPercent = $usedDevice->discount_type === 'percent' ? (float) ($usedDevice->discount_amount ?? 0) : 0;
                $preOwnedDiscountFixed = $usedDevice->discount_type === 'fixed' ? (float) ($usedDevice->discount_amount ?? 0) : 0;

                $preOwnedDiscountAmt = $preOwnedDiscountPercent > 0
                    ? round($regularPrice * $preOwnedDiscountPercent / 100, 2)
                    : $preOwnedDiscountFixed;

                $subTotal += $regularPrice;
                $discountTotal += $preOwnedDiscountAmt;
                $totalBookingAmount += $bookingAmount ? ($usedDevice->booking_amount ?? 0) : 0;

                if (! empty($cart['care_id'])) {
                    $preOwnedCare = $this->cares->findPreOwnedCare($cart['care_id']);
                    $carePercent = (float) ($preOwnedCare?->care?->value ?? 0);
                    $subTotal += round(($regularPrice * 1) * $carePercent / 100, 2);
                }

                $cartItems[] = [
                    'product_id' => $usedDevice->product_id,
                    'product_variant_id' => $usedDevice->product_variant_id,
                    'pre_owned_id' => $usedDevice->id,
                    'product_price' => $regularPrice,
                    'gift_product_variant_id' => $cart['gift_id'] ?? null,
                    'product_care_id' => null,
                    'pre_owned_care_id' => $cart['care_id'] ?? null,
                    'discount_type' => $usedDevice->discount_type,
                    'discount_amount' => $preOwnedDiscountAmt,
                    'purchase_point' => $usedDevice->purchase_point ?? 0,
                    'quantity' => 1,
                ];
            } else {
                $product = $this->products->findById($cart['product_id']);

                if (! $product) {
                    throw new GeneralException('Product not found', 400);
                }

                $variant = $this->variants->findPricedForUpdate($cart['product_variant_id']);

                if (! $variant) {
                    throw new GeneralException('Variant not found', 400);
                }

                if (! $variant->stock && ! $variant->is_prebook) {
                    throw new GeneralException('Stock not available', 400);
                }

                $productVariantPrice = $variant->price?->max_retail_price ?? 0;

                $offer = VariantDiscount::forVariant($variant);

                if (! empty($cart['care_id'])) {
                    $care = $this->cares->findProductCare($cart['care_id']);
                    $careValue = $care?->care?->value ?? 0;
                    $careCharge = ($productVariantPrice * $quantity) * ($careValue / 100);
                    $subTotal += $careCharge;
                }

                $itemPrice = $productVariantPrice * $quantity;
                $itemDiscount = 0;
                if (($offer['discount_type'] ?? null) === 'percent') {
                    $itemDiscount = round(($itemPrice * (float) $offer['discount_amount']) / 100, 2);
                } elseif (($offer['discount_type'] ?? null) === 'fixed') {
                    $itemDiscount = (float) $offer['discount_amount'] * $quantity;
                }

                $subTotal += $itemPrice;
                $discountTotal += $itemDiscount;

                $totalBookingAmount += $bookingAmount ? $product->booking_amount * $quantity : 0;

                $cartItems[] = [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'pre_owned_id' => null,
                    'gift_product_variant_id' => $cart['gift_id'] ?? null,
                    'product_care_id' => $cart['care_id'] ?? null,
                    'pre_owned_care_id' => null,
                    'product_price' => $productVariantPrice,
                    'discount_type' => $offer['discount_type'] ?? null,
                    'discount_amount' => $offer['discount_amount'] ?? 0,
                    'purchase_point' => $product->purchase_point ? $product->purchase_point * $quantity : 0,
                    'quantity' => $quantity,
                ];
            }
        }

        return [$cartItems, $subTotal, $discountTotal, $totalBookingAmount];
    }

    /**
     * Reuse the guest contact (no password) with this email and phone, or create one.
     *
     * @param  array<string, mixed>  $input
     */
    private function findOrCreateGuest(array $input): Contact
    {
        return $this->contacts->findGuest($input['email'], $input['phone'])
            ?? $this->contacts->create([
                'name' => $input['full_name'],
                'email' => $input['email'],
                'phone' => $input['phone'],
                'type' => 'consumer',
            ]);
    }
}
