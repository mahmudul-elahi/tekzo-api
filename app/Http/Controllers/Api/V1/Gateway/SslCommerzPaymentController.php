<?php

namespace App\Http\Controllers\Api\V1\Gateway;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Repositories\Order\OrderRepository;
use App\Services\Payment\SslCommerz\SSLCommerzPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class SslCommerzPaymentController extends Controller
{
    public function __construct(private readonly OrderRepository $orders) {}

    /**
     * SSLCommerz IPN (Instant Payment Notification) — fired server-to-server by SSLCommerz
     * regardless of whether the customer's browser makes it back to our success/fail/cancel
     * URLs, so it's the reliable path for confirming payment. Shares the same validation and
     * order-update logic as the browser redirect via SSLCommerzPaymentService::processCallback,
     * which is a no-op if the order was already resolved by whichever channel got there first.
     */
    public function ipn(Request $request): Response
    {
        $transactionId = $request->input('tran_id');

        if (! $transactionId) {
            return response('Transaction id not found', 400);
        }

        $order = $this->orders->findByTransactionId($transactionId);

        if (! $order instanceof Order) {
            Log::warning('SSLCommerz IPN: order not found', ['tran_id' => $transactionId]);

            return response('Order not found', 404);
        }

        try {
            (new SSLCommerzPaymentService)->processCallback($order, $request->all());
        } catch (\Exception $e) {
            Log::error('SSLCommerz IPN: processing failed', [
                'tran_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            return response('Processing failed', 500);
        }

        return response('OK', 200);
    }
}
