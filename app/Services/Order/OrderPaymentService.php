<?php

namespace App\Services\Order;

use App\Models\Order\Order;
use App\Services\Payment\Bkash\BkashService;
use App\Services\Payment\SslCommerz\SSLCommerzPaymentService;

/**
 * Starts an online payment for an order with the chosen gateway.
 */
class OrderPaymentService
{
    /**
     * The SSLCommerz session for the amount (contains the gateway redirect URL).
     *
     * @return array<string, mixed>
     */
    public function startSslCommerz(Order $order, mixed $amount): array
    {
        $ssl = new SSLCommerzPaymentService;

        return $ssl->initSSLCommerz($order, $amount);
    }

    /**
     * The bKash create-payment result for the amount.
     */
    public function startBkash(mixed $payerReference, mixed $amount, mixed $invoiceNumber): mixed
    {
        return BkashService::make()->createPayment([
            'payerReference' => $payerReference,
            'amount' => $amount,
            'merchantInvoiceNumber' => $invoiceNumber,
        ]);
    }

    /**
     * The amount charged when an order is paid online: the booking amount when one was chosen, otherwise the total.
     */
    public function checkoutAmount(Order $order): mixed
    {
        return $order->total_booking_amount > 0 ? $order->total_booking_amount : $order->grand_total;
    }
}
