<?php

namespace App\Support;

use App\Enums\OnlinePaymentStatusEnum;
use App\Models\Order\Order;

/**
 * The payment method and status labels shown to customers for an order.
 */
class OrderPaymentStatus
{
    /**
     * COD orders always read "Pending"; online/bKash orders map their numeric payment status.
     *
     * @return array{method: string, status: string}
     */
    public static function format(Order $order): array
    {
        $method = match ($order->payment_method) {
            'cod' => 'COD',
            'online' => 'Online',
            'bkash' => 'Bkash',
            default => ucfirst($order->payment_method),
        };

        if ($order->payment_method === 'cod') {
            return [
                'method' => $method,
                'status' => 'Pending',
            ];
        }

        $statusText = match ((int) $order->payment_status) {
            OnlinePaymentStatusEnum::Initiated->value => 'Initiated',
            OnlinePaymentStatusEnum::Processing->value => 'Processing',
            OnlinePaymentStatusEnum::Successful->value => 'Successful',
            OnlinePaymentStatusEnum::Failed->value => 'Failed',
            OnlinePaymentStatusEnum::Canceled->value => 'Cancelled',
            OnlinePaymentStatusEnum::Unsuccessful->value => 'Unsuccessful',
            OnlinePaymentStatusEnum::PartiallyPaid->value => 'Partially Paid',
            default => 'Pending',
        };

        return [
            'method' => $method,
            'status' => $statusText,
        ];
    }
}
