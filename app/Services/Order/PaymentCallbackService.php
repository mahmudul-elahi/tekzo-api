<?php

namespace App\Services\Order;

use App\Enums\OnlinePaymentStatusEnum;
use App\Models\Order\Order;
use App\Repositories\Order\OrderRepository;
use Illuminate\Support\Facades\DB;

/**
 * Handles the customer's browser returning from the payment gateway and decides where to send them.
 * A paid order is never downgraded by a late cancel/fail redirect.
 */
class PaymentCallbackService
{
    public function __construct(private readonly OrderRepository $orders) {}

    /**
     * Frontend URL after a successful payment. The order isn't filtered by status: the
     * SSLCommerz IPN may already have resolved it before the browser redirect arrived.
     */
    public function successRedirect(mixed $transactionId): string
    {
        $order = $this->orders->findByTransactionId($transactionId);

        if (! $order instanceof Order) {
            return $this->frontendUrl('/404');
        }

        return $this->frontendUrl('/order/successful?invoice='.$order->invoice_number);
    }

    /**
     * Mark an unpaid order canceled and return the frontend URL to send the customer to.
     */
    public function cancel(mixed $transactionId): string
    {
        return DB::transaction(function () use ($transactionId) {
            $order = $this->orders->findByTransactionIdForUpdate($transactionId);

            if (! $order instanceof Order) {
                return $this->frontendUrl('/order/failed');
            }

            if ($this->isPaid($order)) {
                return $this->frontendUrl('/order/successful?invoice='.$order->invoice_number);
            }

            $this->resetPayment($order, OnlinePaymentStatusEnum::Canceled->value);

            if ($order->transaction_id && $order->transaction_id == $transactionId) {
                return $this->frontendUrl('/order/canceled?invoice='.$order->invoice_number);
            }

            return $this->frontendUrl('/order/failed');
        });
    }

    /**
     * Mark an unpaid order failed and return the frontend URL to send the customer to.
     */
    public function fail(mixed $transactionId): string
    {
        return DB::transaction(function () use ($transactionId) {
            $order = $this->orders->findByTransactionIdForUpdate($transactionId);

            if (! $order instanceof Order) {
                return $this->frontendUrl('/order/failed');
            }

            if ($this->isPaid($order)) {
                return $this->frontendUrl('/order/successful?invoice='.$order->invoice_number);
            }

            $this->resetPayment($order, OnlinePaymentStatusEnum::Failed->value);

            return $this->frontendUrl('/order/failed');
        });
    }

    private function isPaid(Order $order): bool
    {
        return in_array((int) $order->payment_status, [
            OnlinePaymentStatusEnum::Successful->value,
            OnlinePaymentStatusEnum::PartiallyPaid->value,
        ], true);
    }

    private function resetPayment(Order $order, int $status): void
    {
        $order->payable_amount = 0;
        $order->payment_status = $status;
        $order->due_amount = $order->grand_total;
        $order->additional_value = null;
        $order->save();
    }

    private function frontendUrl(string $path): string
    {
        return config('services.front_base_url').$path;
    }
}
