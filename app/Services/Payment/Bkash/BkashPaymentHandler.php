<?php

namespace App\Services\Payment\Bkash;

use Illuminate\Support\Str;

class BkashPaymentHandler
{
    public function __construct(
        private readonly BkashConfig      $config,
        private readonly BkashHttpClient  $http,
        private readonly BkashTokenManager $tokens,
    ) {}

    public function create(string $payerReference, float $amount, string $invoiceNumber): array
    {
        if ($amount < 1) {
            throw new BkashException('Amount must be at least 1 BDT');
        }

        $response = $this->http->post(
            '/tokenized/checkout/create',
            $this->authHeaders(),
            [
                'mode'                  => '0011',
                'payerReference'        => $payerReference,
                'callbackURL'           => $this->config->websiteUrl . '/api/v1/customer/bkash-callback',
                'amount'                => $amount,
                'currency'              => 'BDT',
                'intent'                => 'sale',
                'merchantInvoiceNumber' => $invoiceNumber ?: 'Inv_' . Str::random(6),
            ]
        );

        if (empty($response['bkashURL'])) {
            throw new BkashException('bKash did not return a payment URL');
        }

        return $response;
    }

    public function execute(string $paymentId): array
    {
        return $this->http->post(
            '/tokenized/checkout/execute',
            $this->authHeaders(),
            ['paymentID' => $paymentId]
        );
    }

    public function query(string $paymentId): array
    {
        return $this->http->post(
            '/tokenized/checkout/payment/status',
            $this->authHeaders(),
            ['paymentID' => $paymentId]
        );
    }

    private function authHeaders(): array
    {
        return [
            'Content-Type:application/json',
            'Authorization:' . $this->tokens->getToken(),
            'X-APP-Key:' . $this->config->appKey,
        ];
    }
}
