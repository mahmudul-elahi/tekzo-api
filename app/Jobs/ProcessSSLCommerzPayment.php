<?php

namespace App\Jobs;

use App\Enums\OnlinePaymentStatusEnum;
use App\Library\SslCommerz\SslCommerzNotification;
use App\Models\Order\Order;
use App\Models\Order\OrderPaymentHistory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessSSLCommerzPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        private readonly array  $postData,
        private readonly Order  $order,
        private readonly string $currency = 'BDT'
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(): void
    {

        $sslcommerz = new SslCommerzNotification();
        $orderTotal = $this->order->total_booking_amount > 0 ? $this->order->total_booking_amount : $this->order->grand_total;
        $isValid = $sslcommerz->orderValidate(
            $this->postData,
            $this->order->transaction_id,
            $orderTotal,
            $this->currency
        );

        // Log::info('SSLCommerz: One', [
        //     'one' => $sslcommerz,
        //     'two' => $isValid,
        //     'three' => $this->postData
        // ]);


        DB::beginTransaction();
        try {
            if ($isValid) {
                $this->markAsSuccess($this->order);
            } else {
                $this->markAsFailed($this->order);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('SSLCommerz: Payment processing failed', [
                'tran_id' => $this->order->transaction_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function markAsSuccess(Order $order): void
    {
        $paidAmount = $order->total_booking_amount > 0 ? $order->total_booking_amount : $order->grand_total;

        $order->payable_amount   = $order->payable_amount + $paidAmount;
        $order->due_amount       = max(0, $order->due_amount - $paidAmount);
        $order->payment_status   = $order->due_amount <= 0
            ? OnlinePaymentStatusEnum::Successful->value
            : OnlinePaymentStatusEnum::PartiallyPaid->value;
        $order->additional_value = json_encode(['payment_info' => $this->postData]);
        $order->save();

        OrderPaymentHistory::create([
            'order_id'         => $order->id,
            'payment_method'   => 'online',
            'amount'           => $paidAmount,
            'transaction_id'   => $order->transaction_id,
            'status'           => OnlinePaymentStatusEnum::Successful->value,
            'gateway_response' => $this->postData,
        ]);

        Log::info('SSLCommerz: payment success', [
            'tran_id'       => $order->transaction_id,
            'paid'          => $paidAmount,
            'due_remaining' => $order->due_amount,
        ]);
    }

    private function markAsFailed(Order $order): void
    {
        $order->payment_status = OnlinePaymentStatusEnum::Failed->value;
        $order->save();

        Log::warning('SSLCommerz: Payment validation failed', ['tran_id' => $order->transaction_id]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SSLCommerz: Job permanently failed', [
            'tran_id' => $this->order->transaction_id,
            'error' => $exception->getMessage()
        ]);

        Order::query()
            ->where('transaction_id', $this->order->transaction_id)
            ->update(['payment_status' => OnlinePaymentStatusEnum::Failed->value]);
    }
}
