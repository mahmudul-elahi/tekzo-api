<?php

namespace App\Http\Controllers\Api\V1\Checkout;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\CheckoutRequest;
use App\Jobs\Order\OrderConfirmJob;
use App\Models\Order\Order;
use App\Services\Order\CheckoutService;
use App\Services\Order\OrderPaymentService;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkoutService,
        private readonly OrderPaymentService $paymentService,
    ) {}

    /**
     * Place the customer's cart as an order. Online and bKash orders return the gateway
     * session; COD orders queue the confirmation and return the invoice number.
     *
     * @throws Throwable
     * @throws GeneralException
     */
    public function checkout(CheckoutRequest $request): mixed
    {
        $customerId = auth()->guard('customer')->id();

        $order = $this->checkoutService->placeOrder($customerId, $request->shipping_address_id, $request->payment_method, $request->booking_amount_only);

        if (! $order instanceof Order) {
            return responseMessage('Cart item not found', 400);
        }

        if ($request->payment_method === 'online') {
            return $this->paymentService->startSslCommerz($order, $this->paymentService->checkoutAmount($order));
        }

        if ($request->payment_method == 'bkash') {
            return response()->json(
                $this->paymentService->startBkash($order->id, $this->paymentService->checkoutAmount($order), $order->invoice_number)
            );
        }

        OrderConfirmJob::dispatch($order);

        return responseMessage(message: 'Checkout successful', data: [
            'invoice_number' => $order->invoice_number,
            'grand_total' => $order->grand_total,
        ]);
    }
}
