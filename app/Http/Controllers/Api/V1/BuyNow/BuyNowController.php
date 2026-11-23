<?php

namespace App\Http\Controllers\Api\V1\BuyNow;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Jobs\Order\OrderConfirmJob;
use App\Services\Order\GuestOrderService;
use App\Services\Order\OrderPaymentService;
use App\Services\Order\PaymentCallbackService;
use App\Services\Payment\Bkash\BkashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class BuyNowController extends Controller
{
    public function __construct(
        private GuestOrderService $guestOrderService,
        private OrderPaymentService $paymentService,
        private PaymentCallbackService $callbackService,
    ) {}

    /**
     * Place a guest order. Online and bKash orders return the gateway session;
     * COD orders queue the confirmation and return the invoice number.
     *
     * @throws GeneralException|Throwable
     */
    public function gustBuyNow(Request $request): mixed
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

        $order = $this->guestOrderService->placeOrder($request->all());

        $amount = $request->booking_amount_only ? $order->total_booking_amount : $order->grand_total;

        if ($request->payment_method === 'online') {
            return $this->paymentService->startSslCommerz($order, $amount);
        }

        if ($request->payment_method == 'bkash') {
            return $this->paymentService->startBkash($order->id, $amount, $order->invoice_number);
        }

        OrderConfirmJob::dispatch($order);

        return responseMessage(message: 'Congratulations! You have successfully placed your order', data: [
            'invoice_number' => $order->invoice_number,
            'grand_total' => $order->grand_total,
        ]);
    }

    /**
     * @throws GeneralException
     */
    public function checkCoupon(Request $request): JsonResponse
    {
        $request->validate([
            'coupon_code' => ['required', 'string'],
            'carts' => ['required', 'array'],
            'carts.*.product_id' => ['required', 'exists:products,id'],
            'carts.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'carts.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        return successResponse($this->guestOrderService->quoteCoupon($request->coupon_code, $request->carts));
    }

    public function success(Request $request): RedirectResponse
    {
        return redirect()->intended($this->callbackService->successRedirect($request->tran_id));
    }

    public function cancel(Request $request): RedirectResponse
    {
        return redirect()->intended($this->callbackService->cancel($request->input('tran_id')));
    }

    public function failed(Request $request): RedirectResponse
    {
        return redirect()->intended($this->callbackService->fail($request->input('tran_id')));
    }

    /**
     * @throws Throwable
     */
    public function bkashCallback(Request $request): mixed
    {
        return BkashService::make()->callback($request);
    }
}
