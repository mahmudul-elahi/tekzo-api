<?php

namespace App\Services\Payment\Bkash;

use App\Enums\OnlinePaymentStatusEnum;
use App\Models\Order\Order;
use App\Models\Order\OrderPaymentHistory;
use Illuminate\Support\Facades\Log;

class BkashOrderUpdater
{
    public function markInitiated(Order $order, string $paymentId): void
    {
        $order->paymentId       = $paymentId;
        $order->payment_status  = OnlinePaymentStatusEnum::Initiated->value;
        $order->save();
    }

    public function markSuccess(Order $order, array $res): void
    {
        $paidAmount = (float) $res['amount'];

        $order->payable_amount   = $order->payable_amount + $paidAmount;
        $order->due_amount       = max(0, $order->due_amount - $paidAmount);
        $order->payment_status   = $order->due_amount <= 0
            ? OnlinePaymentStatusEnum::Successful->value
            : OnlinePaymentStatusEnum::PartiallyPaid->value;
        $order->transaction_id   = $res['trxID'];
        $order->additional_value = json_encode(['payment_info' => $res]);
        $order->save();

        OrderPaymentHistory::create([
            'order_id'         => $order->id,
            'payment_method'   => 'bkash',
            'amount'           => $paidAmount,
            'transaction_id'   => $res['trxID'],
            'status'           => OnlinePaymentStatusEnum::Successful->value,
            'gateway_response' => $res,
        ]);

        Log::info('bKash: payment success', [
            'paymentID'      => $res['paymentID'],
            'paid'           => $paidAmount,
            'due_remaining'  => $order->due_amount,
        ]);
    }

    public function markFailed(Order $order): void
    {
        $order->payment_status = OnlinePaymentStatusEnum::Failed->value;
        $order->save();

        Log::warning('bKash: payment failed', ['paymentID' => $order->paymentId]);
    }

    public function markCanceled(Order $order): void
    {
        $order->payment_status = OnlinePaymentStatusEnum::Canceled->value;
        $order->save();
    }
}
