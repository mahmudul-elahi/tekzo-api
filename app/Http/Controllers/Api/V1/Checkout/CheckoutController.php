<?php

namespace App\Http\Controllers\Api\V1\Checkout;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Jobs\Order\OrderConfirmJob;
use App\Services\Order\CheckoutService;
use App\Services\Order\OrderPaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(
        private CheckoutService $checkoutService,
        private OrderPaymentService $paymentService,
    ) {}

    /**
     * Place the customer's cart as an order. Online and bKash orders return the gateway
     * session; COD orders queue the confirmation and return the invoice number.
     *
     * @throws Throwable
     * @throws GeneralException
     */
    public function checkout(Request $request): mixed
    {
        $customerId = auth()->guard('customer')->id();

        $request->validate([
            'payment_method' => ['required', 'in:cod,online,bkash'],
            'shipping_address_id' => [
                'required',
                Rule::exists('customer_addresses', 'id')->where('contact_id', $customerId),
            ],
            'booking_amount_only' => ['nullable', 'in:0,1'],
        ]);

        $order = $this->checkoutService->placeOrder($customerId, $request->shipping_address_id, $request->payment_method, $request->booking_amount_only);

        if (! $order) {
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
