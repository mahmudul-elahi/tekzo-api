<?php

namespace App\Http\Controllers\Api\V1\BuyNow;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Http\Requests\BuyNow\GuestCouponCheckRequest;
use App\Http\Requests\BuyNow\GuestOrderRequest;
use App\Jobs\Order\OrderConfirmJob;
use App\Services\Order\GuestOrderService;
use App\Services\Order\OrderPaymentService;
use App\Services\Order\PaymentCallbackService;
use App\Services\Payment\Bkash\BkashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class BuyNowController extends Controller
{
    public function __construct(
        private readonly GuestOrderService $guestOrderService,
        private readonly OrderPaymentService $paymentService,
        private readonly PaymentCallbackService $callbackService,
    ) {}

    /**
     * Place a guest order. Online and bKash orders return the gateway session;
     * COD orders queue the confirmation and return the invoice number.
     *
     * @throws GeneralException|Throwable
     */
    public function gustBuyNow(GuestOrderRequest $request): mixed
    {
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
    public function checkCoupon(GuestCouponCheckRequest $request): JsonResponse
    {
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
