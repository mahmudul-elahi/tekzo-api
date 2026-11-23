<?php

namespace App\Http\Controllers\Api\V1\BuyNow;

use App\Enums\OnlinePaymentStatusEnum;
use App\Enums\StatusEnum;
use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Jobs\Order\OrderConfirmJob;
use App\Models\Care\PreOwnedCare;
use App\Models\Care\ProductCare;
use App\Models\Contact\Contact;
use App\Models\Coupon\CustomerCouponLog;
use App\Models\Coupon\NewCoupon;
use App\Models\Order\Order;
use App\Models\Order\OrderList;
use App\Models\PreOwned\PreOwned;
use App\Models\Product\Product;
use App\Models\Product\ProductVariant;
use App\Services\Payment\Bkash\BkashService;
use App\Services\Shipping\ShippingChargeService;
use App\Services\Payment\SslCommerz\SSLCommerzPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class BuyNowController extends Controller
{
    /**
     * @throws GeneralException|Throwable
     */
    public function gustBuyNow(Request $request)
    {

        $rules = [
            'payment_method' => ['required', 'in:cod,online,bkash'],
            'booking_amount_only' => ['nullable', 'in:0,1'],
            'email' => ['required', 'indisposable', 'email', 'max:100'],
            'phone' => ['required', 'max:13'],
            'full_name' => ['required', 'max:100'],
            'district_id' => ['required', 'exists:new_districts,id'],
            'area_id' => ['required', 'exists:areas,id'],
            'address' => ['required', 'max:500'],
            'note' => ['nullable', 'max:500'],
            'is_agree' => ['required', 'in:1'],
            'carts' => ['required', 'array'],
            'carts.*.product_id' => ['required', 'exists:products,id'],
            'carts.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'carts.*.pre_owned_id' => ['nullable', 'exists:pre_owneds,id'],
            'carts.*.quantity' => ['required', 'integer', 'min:1'],
            'carts.*.gift_id' => ['nullable', Rule::exists('gift_product_variants', 'id')->whereNull('deleted_at')],
            'coupon_code' => ['nullable', 'string'],
        ];

        foreach ($request->input('carts', []) as $i => $cart) {
            $rules["carts.$i.care_id"] = ! empty($cart['pre_owned_id'])
                ? ['nullable', Rule::exists('pre_owned_cares', 'id')->whereNull('deleted_at')]
                : ['nullable', Rule::exists('product_cares', 'id')->whereNull('deleted_at')];
        }

        $request->validate($rules);

        try {
            $order = DB::transaction(function () use ($request) {
                [$cartItems, $subTotal, $discountTotal, $totalBookingAmount] = $this->processCartItems($request->carts, (bool) $request->booking_amount_only);

                $shippingCharge = ShippingChargeService::resolveForArea((int) $request->district_id, (int) $request->area_id);
                $grandTotal = ($subTotal - $discountTotal) + $shippingCharge;

                $couponCode = null;
                $couponDiscount = 0;
                $appliedCoupon = null;

                if ($request->filled('coupon_code')) {
                    [$appliedCoupon, $couponCode, $couponDiscount] = $this->resolveCoupon($request->coupon_code, $cartItems, $grandTotal);
                    $grandTotal = max($grandTotal - $couponDiscount, 0);
                }

                $contact = $this->resolveContact($request);

                $order = $this->createOrder($contact->id, $request, $totalBookingAmount, $subTotal, $shippingCharge, $grandTotal, $couponCode, $couponDiscount);

                $this->createOrderItems($order->id, $cartItems);

                if ($appliedCoupon) {
                    CustomerCouponLog::create([
                        'new_coupon_id' => $appliedCoupon->id,
                        'contact_id' => $contact->id,
                        'used_at' => now(),
                    ]);
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

        if ($request->payment_method === 'online') {
            $amount = $request->booking_amount_only ? $order->total_booking_amount : $order->grand_total;
            $ssl = new SSLCommerzPaymentService;

            return $ssl->initSSLCommerz($order, $amount);
        }

        if ($request->payment_method == 'bkash') {
            $amount = $request->booking_amount_only ? $order->total_booking_amount : $order->grand_total;

            return BkashService::make()->createPayment([
                'payerReference' => $order->id,
                'amount' => $amount,
                'merchantInvoiceNumber' => $order->invoice_number,
            ]);
        }

        OrderConfirmJob::dispatch($order);

        return responseMessage(message: 'Congratulations! You have successfully placed your order', data: [
            'invoice_number' => $order->invoice_number,
            'grand_total' => $order->grand_total,
        ]);
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    /**
     * Build cart item data and compute totals.
     */
    private function processCartItems(array $carts, bool $bookingAmount): array
    {
        $subTotal = 0;
        $discountTotal = 0;
        $cartItems = [];
        $totalBookingAmount = 0;

        foreach ($carts as $cart) {

            $quantity = $cart['quantity'];
            $usedDevice = null;

            // ONLY check preowned if pre_owned_id exists
            if (isset($cart['pre_owned_id']) && ! empty($cart['pre_owned_id'])) {
                //  quantity validation for preowned (optional but recommended)
                if ($quantity > 1) {
                    throw new GeneralException('Pre-owned device quantity must be 1', 400);
                }

                $usedDevice = PreOwned::query()
                    ->where('id', $cart['pre_owned_id'])
                    ->where('product_variant_id', $cart['product_variant_id'])
                    ->where('is_sold', false)
                    ->lockForUpdate() // prevent race condition
                    ->first();

                if (! $usedDevice) {
                    throw new GeneralException('This device is already sold', 400);
                }
            }

            // =========================
            // PRE-OWNED PRODUCT
            // =========================
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
                    $preOwnedCare = PreOwnedCare::with('care')
                        ->find($cart['care_id']);
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
            }

            // =========================
            // NORMAL PRODUCT
            // =========================
            else {
                $product = Product::find($cart['product_id']);

                if (! $product) {
                    throw new GeneralException('Product not found', 400);
                }

                $variant = ProductVariant::with(['price', 'offerProduct'])
                    ->where('id', $cart['product_variant_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $variant) {
                    throw new GeneralException('Variant not found', 400);
                }

                if (! $variant || (! $variant->stock && ! $variant->is_prebook)) {
                    throw new GeneralException('Stock not available', 400);
                }

                $productVariantPrice = $variant->price?->max_retail_price ?? 0;

                /**
                 * ------------------------
                 * DISCOUNT CALCULATION
                 * ------------------------
                 */
                if ($variant?->offerProduct?->discount_amount > 0) {
                    $offer = $this->handelDiscountType($variant->offerProduct);
                } else {
                    $offer = [
                        'discount_type' => $variant->price?->discount_percent > 0 ? 'percent' : ($variant->price?->discount_fixed > 0 ? 'fixed' : null),

                        'discount_amount' => $variant->price?->discount_percent > 0
                            ? $variant->price->discount_percent
                            : ($variant->price?->discount_fixed ?? 0),
                    ];
                }

                if (! empty($cart['care_id'])) {
                    $care = ProductCare::with('care')
                        ->find($cart['care_id']);
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

    private function handelDiscountType($offerProduct)
    {

        return [
            'discount_type' => $offerProduct->discount_type === 'percentage' ? 'percent' : 'fixed',
            'discount_amount' => $offerProduct->discount_amount,
        ];
    }

    /**
     * Find existing contact by email and phone (guests only), or create a new guest contact.
     */
    private function resolveContact(Request $request): Contact
    {
        return Contact::query()
            ->where('email', $request->email)
            ->where('phone', $request->phone)
            ->whereNull('password')
            ->first()
            ?? Contact::query()->create([
                'name' => $request->full_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'type' => 'consumer',
            ]);
    }

    /**
     * Create and persist the Order record.
     */
    private function createOrder(
        int $contactId,
        Request $request,
        float $totalBookingAmount,
        float $subTotal,
        float $shippingCharge,
        float $grandTotal,
        ?string $couponCode = null,
        float $couponDiscount = 0
    ): Order {
        return Order::query()->create([
            'contact_id' => $contactId,
            'payment_method' => $request->payment_method,
            'payment_status' => null,
            'new_district_id' => $request->district_id,
            'area_id' => $request->area_id,
            'address' => $request->address,
            'total_booking_amount' => $totalBookingAmount,
            'sub_total' => $subTotal,
            'shipping_charge' => $shippingCharge,
            'coupon_code' => $couponCode,
            'discount_total' => $couponDiscount,
            'grand_total' => $grandTotal,
            'due_amount' => $grandTotal,
            'payable_amount' => 0,
            'status' => StatusEnum::Pending->value,
            'customer_note' => $request->note,
            'custom_value' => json_encode([
                'customer_name' => $request->full_name,
            ]),
        ]);
    }

    public function checkCoupon(Request $request)
    {
        $request->validate([
            'coupon_code' => ['required', 'string'],
            'carts' => ['required', 'array'],
            'carts.*.product_id' => ['required', 'exists:products,id'],
            'carts.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'carts.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        [$cartItems, $subTotal, $discountTotal] = $this->processCartItems($request->carts, false);

        $grandTotal = $subTotal - $discountTotal;

        [, $couponCode, $couponDiscount] = $this->resolveCoupon($request->coupon_code, $cartItems, $grandTotal);

        return successResponse([
            'coupon_code' => $couponCode,
            'sub_total' => $grandTotal,
            'discount_amount' => $couponDiscount,
            'grand_total' => max($grandTotal - $couponDiscount, 0),
        ]);
    }

    private function resolveCoupon(string $code, array $cartItems, float $grandTotal): array
    {
        $coupon = NewCoupon::where('code', $code)
            ->where('status', true)
            ->where(fn ($q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', now()))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', now()))
            ->with(['productScopes'])
            ->withCount('usages')
            ->first();

        if (! $coupon) {
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

        $applicableTotal = $this->getGuestApplicableTotal($cartItems, $coupon);

        if ($applicableTotal <= 0) {
            throw new GeneralException('Coupon is not applicable to any items in your order', 400);
        }

        $discount = $coupon->discount_type === 'percent'
            ? ($applicableTotal * $coupon->discount_value / 100)
            : $coupon->discount_value;

        return [$coupon, $coupon->code, min(round($discount, 2), $grandTotal)];
    }

    private function getGuestApplicableTotal(array $cartItems, NewCoupon $coupon): float
    {
        $productIds = array_unique(array_column($cartItems, 'product_id'));
        $preOwnedIds = array_values(array_filter(array_column($cartItems, 'pre_owned_id')));

        if ($coupon->productScopes->isEmpty()) {
            return $this->sumGuestItems($cartItems, $productIds, $preOwnedIds);
        }

        $applicableProductIds = [];
        $applicablePreOwnedIds = [];

        $products = Product::with(['brand', 'category'])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

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

        return $this->sumGuestItems($cartItems, $applicableProductIds, $applicablePreOwnedIds);
    }

    private function sumGuestItems(array $cartItems, array $productIds, array $preOwnedIds = []): float
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
        }, $cartItems));
    }

    /**
     * Insert all OrderList rows for the given order.
     */
    private function createOrderItems(int $orderId, array $cartItems): void
    {
        $rows = array_map(fn ($item) => array_merge($item, ['order_id' => $orderId]), $cartItems);

        OrderList::query()->insert($rows);
    }

    /**
     * Generate a unique invoice number.
     */
    private function generateInvoiceNumber()
    {
        $maxOrderId = Order::query()->max('id') ?? 0;

        return 'ORD-'.date('Ymd').'-'.str_pad($maxOrderId + 1, 6, '0', STR_PAD_LEFT);
    }

    public function success(Request $request)
    {
        $tranId = $request->tran_id;

        // Find order by transaction_id (not filtered by status: the SSLCommerz IPN may
        // have already resolved this order before the browser redirect got here)
        $order = Order::query()
            ->where('transaction_id', $tranId)
            ->first();

        if (! $order) {
            return redirect()->intended(config('services.front_base_url').'/404');
        }

        if (in_array($order->payment_status, [
            OnlinePaymentStatusEnum::Successful->value,
            OnlinePaymentStatusEnum::PartiallyPaid->value,
        ], true)) {
            return redirect()->intended(config('services.front_base_url').'/order/successful?invoice='.$order->invoice_number);
        }

        return redirect()->intended(config('services.front_base_url').'/order/successful?invoice='.$order->invoice_number);
    }

    public function cancel(Request $request)
    {
        $tran_id = $request->input('tran_id');

        return DB::transaction(function () use ($tran_id) {
            $order = Order::query()->where('transaction_id', $tran_id)->lockForUpdate()->first();

            if (! $order) {
                return redirect()->intended(config('services.front_base_url').'/order/failed');
            }

            if (in_array((int) $order->payment_status, [
                OnlinePaymentStatusEnum::Successful->value,
                OnlinePaymentStatusEnum::PartiallyPaid->value,
            ], true)) {
                return redirect()->intended(config('services.front_base_url').'/order/successful?invoice='.$order->invoice_number);
            }

            $order->payable_amount = 0;
            $order->payment_status = OnlinePaymentStatusEnum::Canceled->value;
            $order->due_amount = $order->grand_total;
            $order->additional_value = null;
            $order->save();

            if ($order->transaction_id && $order->transaction_id == $tran_id) {
                return redirect()->intended(config('services.front_base_url').'/order/canceled?invoice='.$order->invoice_number);
            }

            return redirect()->intended(config('services.front_base_url').'/order/failed');
        });
    }

    public function failed(Request $request)
    {
        $tran_id = $request->input('tran_id');

        return DB::transaction(function () use ($tran_id) {
            $order = Order::query()->where('transaction_id', $tran_id)->lockForUpdate()->first();

            if (! $order) {
                return redirect()->intended(config('services.front_base_url').'/order/failed');
            }

            if (in_array((int) $order->payment_status, [
                OnlinePaymentStatusEnum::Successful->value,
                OnlinePaymentStatusEnum::PartiallyPaid->value,
            ], true)) {
                return redirect()->intended(config('services.front_base_url').'/order/successful?invoice='.$order->invoice_number);
            }

            $order->payable_amount = 0;
            $order->payment_status = OnlinePaymentStatusEnum::Failed->value;
            $order->due_amount = $order->grand_total;
            $order->additional_value = null;
            $order->save();

            return redirect()->intended(config('services.front_base_url').'/order/failed');
        });
    }

    /**
     * @throws Throwable
     */
    public function bkashCallback(Request $request)
    {
        return BkashService::make()->callback($request);
    }
}
