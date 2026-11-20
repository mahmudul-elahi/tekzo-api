<?php

namespace App\Services\Bkash;

use App\Models\Order\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BkashService
{
    public function __construct(
        private readonly BkashPaymentHandler $payments,
        private readonly BkashOrderUpdater   $orders,
    ) {}

    public static function make(): self
    {
        $config  = BkashConfig::fromConfig();
        $http    = new BkashHttpClient($config);
        $tokens  = new BkashTokenManager($config, $http);
        $handler = new BkashPaymentHandler($config, $http, $tokens);
        $updater = new BkashOrderUpdater();

        return new self($handler, $updater);
    }

    public function createPayment(array $data): array
    {
        $order = Order::query()->where('invoice_number', $data['merchantInvoiceNumber'])->first();

        if (!$order) {
            throw new BkashException('Order not found for invoice: ' . $data['merchantInvoiceNumber']);
        }

        $response = $this->payments->create(
            (string) $data['payerReference'],
            (float)  $data['amount'],
            $data['merchantInvoiceNumber']
        );

        $this->orders->markInitiated($order, $response['paymentID']);

        return ['message' => 'success', 'url' => $response['bkashURL']];
    }

    /**
     * @throws Throwable
     */
    public function callback(Request $request): \Illuminate\Http\RedirectResponse
    {
        $params = $request->all();
        $frontUrl = config('services.front_base_url');

        $order = Order::query()->where('paymentId', $params['paymentID'])->first();

        if (!$order) {
            return redirect("{$frontUrl}/order/failed");
        }

        return match ($params['status'] ?? 'failure') {
            'success' => $this->handleSuccess($order, $params['paymentID'], $frontUrl),
            'cancel'  => $this->handleCancel($order, $frontUrl),
            default   => $this->handleFailure($order, $frontUrl),
        };
    }

    /**
     * @throws Throwable
     */
    private function handleSuccess(Order $order, string $paymentId, string $frontUrl): \Illuminate\Http\RedirectResponse
    {
        $res = $this->payments->execute($paymentId);

        if (empty($res['statusCode'])) {
            sleep(1);
            $res = $this->payments->query($paymentId);
        }

        DB::beginTransaction();
        try {
            if (($res['statusCode'] ?? null) === '0000' && ($res['transactionStatus'] ?? null) === 'Completed') {
                $this->orders->markSuccess($order, $res);
                DB::commit();
                return redirect("{$frontUrl}/order/successful?invoice_number={$order->invoice_number}");
            }

            $this->orders->markFailed($order);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('bKash: DB update failed', ['paymentID' => $paymentId, 'error' => $e->getMessage()]);
            $this->orders->markFailed($order);
        }

        return redirect("{$frontUrl}/order/failed?invoice={$order->invoice_number}");
    }

    private function handleCancel(Order $order, string $frontUrl): \Illuminate\Http\RedirectResponse
    {
        $this->orders->markCanceled($order);
        return redirect("{$frontUrl}/order/failed?invoice={$order->invoice_number}&cancel");
    }

    private function handleFailure(Order $order, string $frontUrl): \Illuminate\Http\RedirectResponse
    {
        $this->orders->markFailed($order);
        return redirect("{$frontUrl}/order/failed?invoice={$order->invoice_number}&failure");
    }
}
