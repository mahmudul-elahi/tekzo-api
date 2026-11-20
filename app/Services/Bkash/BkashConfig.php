<?php

namespace App\Services\Bkash;

readonly class BkashConfig
{
    public function __construct(
        public string $baseUrl,
        public string $username,
        public string $password,
        public string $appKey,
        public string $appSecret,
        public string $websiteUrl,
    )
    {
    }

    public static function fromConfig(): self
    {
        return new self(
            baseUrl: config('bkash.baseUrl'),
            username: config('bkash.username'),
            password: config('bkash.password'),
            appKey: config('bkash.app_key'),
            appSecret: config('bkash.app_secret'),
            websiteUrl: config('app.url'),
        );
    }
}
