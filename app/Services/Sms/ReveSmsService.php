<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class ReveSmsService
{
    /**
     * Send an SMS via the REVE SMS gateway.
     *
     * @return array{success: bool, message_id?: mixed, message?: string}
     */
    public function send(string $phoneNumber, string $messageContent): array
    {
        $apiKey = Config::get('services.reve_sms.api_key');
        $secret = Config::get('services.reve_sms.secret_key');
        $sender = Config::get('services.reve_sms.sender_id');
        $endpoint = Config::get('services.reve_sms.endpoint');

        $response = Http::timeout(10)->get($endpoint, [
            'apikey' => $apiKey,
            'secretkey' => $secret,
            'callerID' => $sender,
            'toUser' => $phoneNumber,
            'messageContent' => $messageContent,
        ]);

        $data = $response->json();

        if (isset($data['Status']) && (string) $data['Status'] === '0') {
            return ['success' => true, 'message_id' => $data['Message_ID'] ?? null];
        }

        $errorMessage = match ((string) ($data['Status'] ?? '')) {
            '109' => 'User not provided/deleted or API key is missing.',
            '108' => 'Wrong password or not provided.',
            '114' => 'Content not provided.',
            '101' => 'Internal server error.',
            '1' => 'Request failed.',
            default => 'An unknown error occurred.',
        };

        return ['success' => false, 'message' => $errorMessage];
    }
}
