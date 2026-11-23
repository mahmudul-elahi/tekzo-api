<?php

namespace App\Services\Payment\Bkash;

use App\Enums\OnlinePaymentStatusEnum;
use App\Models\Order\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class BkashService
{
    public function __construct(
        private readonly BkashPaymentHandler $payments,
        private readonly BkashOrderUpdater $orders,
    ) {}

    public static function make(): self
    {
        $config = BkashConfig::fromConfig();
        $http = new BkashHttpClient($config);
        $tokens = new BkashTokenManager($config, $http);
        $handler = new BkashPaymentHandler($config, $http, $tokens);
        $updater = new BkashOrderUpdater;

        return new self($handler, $updater);
    }

    public function createPayment(array $data): array
    {
        $order = Order::query()->where('invoice_number', $data['merchantInvoiceNumber'])->first();

        if (! $order) {
            throw new BkashException('Order not found for invoice: '.$data['merchantInvoiceNumber']);
        }

        $response = $this->payments->create(
            (string) $data['payerReference'],
            (float) $data['amount'],
            $data['merchantInvoiceNumber']
        );

        $this->orders->markInitiated($order, $response['paymentID']);

        return ['message' => 'success', 'url' => $response['bkashURL']];
    }

    /**
     * @throws Throwable
     */
    public function callback(Request $request): RedirectResponse
    {
        $params = $request->all();
        $frontUrl = config('services.front_base_url');

        $order = Order::query()->where('paymentId', $params['paymentID'])->first();

        if (! $order) {
            return redirect("{$frontUrl}/order/failed");
        }

        if (in_array((int) $order->payment_status, [
            OnlinePaymentStatusEnum::Successful->value,
            OnlinePaymentStatusEnum::PartiallyPaid->value,
        ], true)) {
            return redirect("{$frontUrl}/order/successful?invoice_number={$order->invoice_number}");
        }

        return match ($params['status'] ?? 'failure') {
            'success' => $this->handleSuccess($order, $params['paymentID'], $frontUrl),
            'cancel' => $this->handleCancel($order, $frontUrl),
            default => $this->handleFailure($order, $frontUrl),
        };
    }

    /**
     * @throws Throwable
     */
    private function handleSuccess(Order $order, string $paymentId, string $frontUrl): RedirectResponse
    {
        return DB::transaction(function () use ($order, $paymentId, $frontUrl) {
            $lockedOrder = Order::query()->lockForUpdate()->find($order->id);

            if (! $lockedOrder) {
                return redirect("{$frontUrl}/order/failed");
            }

            if (in_array((int) $lockedOrder->payment_status, [
                OnlinePaymentStatusEnum::Successful->value,
                OnlinePaymentStatusEnum::PartiallyPaid->value,
            ], true)) {
                return redirect("{$frontUrl}/order/successful?invoice_number={$lockedOrder->invoice_number}");
            }

            $res = $this->payments->execute($paymentId);

            if (empty($res['statusCode'])) {
                sleep(1);
                $res = $this->payments->query($paymentId);
            }

            if (($res['statusCode'] ?? null) === '0000' && ($res['transactionStatus'] ?? null) === 'Completed') {
                $this->orders->markSuccess($lockedOrder, $res);

                return redirect("{$frontUrl}/order/successful?invoice_number={$lockedOrder->invoice_number}");
            }

            $this->orders->markFailed($lockedOrder);

            return redirect("{$frontUrl}/order/failed?invoice={$lockedOrder->invoice_number}");
        });
    }

    private function handleCancel(Order $order, string $frontUrl): RedirectResponse
    {
        return DB::transaction(function () use ($order, $frontUrl) {
            $lockedOrder = Order::query()->lockForUpdate()->find($order->id);

            if ($lockedOrder && in_array((int) $lockedOrder->payment_status, [
                OnlinePaymentStatusEnum::Successful->value,
                OnlinePaymentStatusEnum::PartiallyPaid->value,
            ], true)) {
                return redirect("{$frontUrl}/order/successful?invoice_number={$lockedOrder->invoice_number}");
            }

            if ($lockedOrder) {
                $this->orders->markCanceled($lockedOrder);
            }

            return redirect("{$frontUrl}/order/failed?invoice={$order->invoice_number}&cancel");
        });
    }

    private function handleFailure(Order $order, string $frontUrl): RedirectResponse
    {
        return DB::transaction(function () use ($order, $frontUrl) {
            $lockedOrder = Order::query()->lockForUpdate()->find($order->id);

            if ($lockedOrder && in_array((int) $lockedOrder->payment_status, [
                OnlinePaymentStatusEnum::Successful->value,
                OnlinePaymentStatusEnum::PartiallyPaid->value,
            ], true)) {
                return redirect("{$frontUrl}/order/successful?invoice_number={$lockedOrder->invoice_number}");
            }

            if ($lockedOrder) {
                $this->orders->markFailed($lockedOrder);
            }

            return redirect("{$frontUrl}/order/failed?invoice={$order->invoice_number}&failure");
        });
    }
}
