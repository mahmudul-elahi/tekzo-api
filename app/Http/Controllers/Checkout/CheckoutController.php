<?php

namespace App\Http\Controllers\Checkout;

use App\Enums\OnlinePaymentStatusEnum;
use App\Enums\StatusEnum;
use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Jobs\Order\OrderConfirmJob;
use App\Models\Address\CustomerAddress;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Coupon\CustomerCouponLog;
use App\Models\Order\Order;
use App\Models\Order\OrderList;
use App\Services\Bkash\BkashService;
use App\Services\Cart\CartService;
use App\Services\SSCommerz\SSLCommerzPaymentService;
use App\Services\Shipping\ShippingChargeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckoutController extends Controller
{
    /**
     * @throws Throwable
     * @throws GeneralException
     */
    public function checkout(Request $request, CartService $cartService)
    {
        $request->validate([
            'payment_method' => ['required', 'in:cod,online,bkash'],
            'shipping_address_id' => ['required', 'exists:customer_addresses,id'],
            'booking_amount_only' => ['nullable', 'in:0,1'],
        ]);

        $customerId = auth()->guard('customer')->id();

        // Fetch cart with items
        $cart = Cart::query()
            ->where('contact_id', $customerId)
            ->with(['cartItems.product', 'coupon'])
            ->first();

        if (!$cart || $cart->cartItems->isEmpty()) {
            return responseMessage('Cart item not found', 400);
        }

        $cartService->recalculate($cart);
        $cart->refresh();

        $shippingAddress = CustomerAddress::query()->find($request->shipping_address_id);
        $shippingCharge = ShippingChargeService::resolveForArea(
            (int) $shippingAddress->new_district_id,
            (int) $shippingAddress->area_id
        );
        $grandTotal = $cart->grand_total + $shippingCharge;

        try {
            DB::beginTransaction();

            // Generate invoice number
            $maxOrderId = Order::query()->max('id') ?? 0;
            $formattedInvoiceNumber = 'ORD-' . date('Ymd') . '-' . str_pad($maxOrderId + 1, 6, '0', STR_PAD_LEFT);

            // Create Order
            $order = Order::query()->create([
                'contact_id' => $customerId,
                'shipping_address_id' => $request->shipping_address_id,
                'coupon_code' => $cart->coupon?->code,
                'discount_total' => $cart->coupon_discount,
                'total_booking_amount' => $request->booking_amount_only ? $cart->total_booking_amount : 0,
                'sub_total' => $cart->sub_total,
                'shipping_charge' => $shippingCharge,
                'due_amount' => $grandTotal,
                'payable_amount' => 0,
                'grand_total' => $grandTotal,
                'payment_method' => $request->payment_method,
                'payment_status' => OnlinePaymentStatusEnum::Pending->value,
                'status' => StatusEnum::Pending->value,
                'invoice_number' => $formattedInvoiceNumber,
            ]);

            // Create OrderItems
            foreach ($cart->cartItems as $item) {
                $unitPrice = (float) ($item->unit_price ?? 0);

                OrderList::query()->create([
                    'order_id'               => $order->id,
                    'product_id'             => $item->product_id,
                    'product_variant_id'     => $item->product_variant_id,
                    'pre_owned_id'           => $item->pre_owned_id,
                    'gift_product_variant_id' => $item->gift_product_variant_id,
                    'product_care_id'        => $item->product_care_id,
                    'pre_owned_care_id'      => $item->pre_owned_care_id,
                    'product_price'          => $unitPrice,
                    'quantity'               => $item->quantity,
                    'purchase_point'          => $item->pre_owned_id
                        ? ($item->preOwned?->purchase_point ?? 0)
                        : (($item->product?->purchase_point ?? 0) * ($item->quantity ?? 0)),
                    'discount_type'          => $item->discount_type,
                    'discount_amount'        => $item->discount_amount,
                ]);
            }

            // Clear purchased cart items
            CartItem::query()->whereIn('id', $cart->cartItems->pluck('id'))->delete();

            // Log coupon usage and reset cart coupon
            if ($cart->coupon_id) {
                CustomerCouponLog::create([
                    'new_coupon_id' => $cart->coupon_id,
                    'contact_id'    => $customerId,
                    'used_at'       => now(),
                ]);

                $cart->update([
                    'coupon_id'       => null,
                    'coupon_discount' => 0,
                ]);
            }

            DB::commit();

            if ($request->payment_method === 'online') {

                $amount = $order->total_booking_amount > 0 ? $order->total_booking_amount : $order->grand_total;

                $finalAmount = $amount;

                $ssl = new SSLCommerzPaymentService();
                return $ssl->initSSLCommerz($order, $finalAmount);
            }

            if ($request->payment_method == 'bkash') {

                $amount = $order->total_booking_amount > 0 ? $order->total_booking_amount : $order->grand_total;
                $finalAmount = $amount;

                $result = BkashService::make()->createPayment([
                    'payerReference'        => $order->id,
                    'amount'                => $finalAmount,
                    'merchantInvoiceNumber' => $order->invoice_number,
                ]);

                return response()->json($result);
            }

            OrderConfirmJob::dispatch($order);

            return responseMessage(message: 'Checkout successful', data: [
                'invoice_number' => $order->invoice_number,
                'grand_total' => $order->grand_total,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            if (app()->environment('production')) {
                throw new GeneralException('Checkout failed, please try again later', 500);
            }
            throw new GeneralException($e->getMessage(), 500);
        }
    }

    /**
     * Calculate final price after discount
     */
    //    private function calculateDiscountPrice(float $unitPrice, ?string $discountType, ?float $discountAmount): float
    //    {
    //        if ($discountType === 'percent') {
    //            return $unitPrice - ($unitPrice * $discountAmount / 100);
    //        } elseif ($discountType === 'fixed') {
    //            return max($unitPrice - $discountAmount, 0);
    //        }
    //
    //        return $unitPrice;
    //    }
}
