<?php

namespace App\Services\Payment\Bkash;

use Illuminate\Support\Facades\Log;

class BkashHttpClient
{
    public function __construct(private readonly BkashConfig $config) {}

    public function post(string $path, array $headers, array $body): array
    {
        $curl = curl_init($this->config->baseUrl.$path);

        curl_setopt_array($curl, [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($error) {
            Log::error("bKash cURL error: {$error}");
            throw new BkashException("cURL error: {$error}");
        }

        if ($httpCode >= 400) {
            Log::error("bKash HTTP {$httpCode}", ['response' => $response]);
            throw new BkashException("HTTP error {$httpCode}");
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BkashException('Invalid JSON response from bKash');
        }

        return $decoded;
    }
}
