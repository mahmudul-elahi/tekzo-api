<?php

namespace App\Jobs;

use App\Enums\OnlinePaymentStatusEnum;
use App\Models\Order\Order;
use App\Services\Bkash\BkashService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessBkashPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries  = 3;
    public int $backoff = 10;

    public function __construct(
        private readonly string $paymentID,
        private readonly string $invoiceNumber,
        private readonly float  $paidAmount,
    ) {}

    public function handle(): void
    {
        $order = Order::query()
            ->where('paymentId', $this->paymentID)
            ->first();

        if (!$order) {
            Log::error('bKash Job: Order not found', ['paymentID' => $this->paymentID]);
            return;
        }

        //Duplicate processing prevent
        if ($order->payment_status === OnlinePaymentStatusEnum::Successful->value) {
            return;
        }

        $bkash = new BkashService();
        $response = $bkash->executePayment($this->paymentID);

        // execute fail
        if (is_null($response)) {
            sleep(1);
            $response = $bkash->queryPayment($this->paymentID);
        }

        $res = json_decode($response, true);

        DB::beginTransaction();
        try {
            if (
                isset($res['statusCode'], $res['transactionStatus']) &&
                $res['statusCode'] === '0000' &&
                $res['transactionStatus'] === 'Completed'
            ) {
                $this->markSuccess($order, $res);
            } else {
                $this->markFailed($order);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('bKash Job: DB update failed', [
                'paymentID' => $this->paymentID,
                'error'     => $e->getMessage(),
            ]);
            throw $e; // retry trigger
        }
    }

    private function markSuccess(Order $order, array $res): void
    {
       // $bookingAmount = $order->total_booking_amount ?? 0;

       ///$order->payable_amount  = $bookingAmount > 0 ? $bookingAmount : $order->grand_total;
       $order->payable_amount  =  $order->grand_total;
        //$order->due_amount      = $bookingAmount > 0 ? $order->grand_total - $bookingAmount : 0;
        $order->due_amount      = $order->grand_total - $this->paidAmount;
        $order->payment_status  = OnlinePaymentStatusEnum::Successful->value;
        $order->transaction_id  = $res['trxID'];
        $order->save();

        Log::info('bKash Job: Payment successful', ['paymentID' => $this->paymentID]);
    }

    private function markFailed(Order $order): void
    {
        $order->payment_status = OnlinePaymentStatusEnum::Failed->value;
        $order->save();

        Log::warning('bKash Job: Payment failed', ['paymentID' => $this->paymentID]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('bKash Job: Permanently failed', [
            'paymentID' => $this->paymentID,
            'error'     => $e->getMessage(),
        ]);

        Order::query()
            ->where('paymentId', $this->paymentID)
            ->update(['payment_status' => OnlinePaymentStatusEnum::Failed->value]);
    }
}
