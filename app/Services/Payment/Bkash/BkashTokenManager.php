<?php

namespace App\Services\Payment\Bkash;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BkashTokenManager
{
    public function __construct(
        private readonly BkashConfig     $config,
        private readonly BkashHttpClient $http,
    )
    {
    }

    public function getToken(): string
    {
        $cached = DB::table('bkash_tokens')->first();

        if ($cached && Carbon::parse($cached->expires_at)->isFuture()) {
            return $cached->token;
        }

        return $this->fetchAndCacheToken();
    }

    private function fetchAndCacheToken(): string
    {
        $headers = [
            'Content-Type:application/json',
            'Accept:application/json',
            'username:' . $this->config->username,
            'password:' . $this->config->password,
        ];

        $response = $this->http->post(
            '/tokenized/checkout/token/grant',
            $headers,
            ['app_key' => $this->config->appKey, 'app_secret' => $this->config->appSecret]
        );

        if (empty($response['id_token'])) {
            throw new BkashException('Failed to obtain bKash token');
        }

        DB::table('bkash_tokens')->updateOrInsert(
            ['id' => 1],
            [
                'token' => $response['id_token'],
                'expires_at' => Carbon::now()->addSeconds(3300),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        );

        return $response['id_token'];
    }
}
