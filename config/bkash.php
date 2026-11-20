<?php
// Bkash configuration
$base_url = env('BKASH_SANDBOX') ? 'https://tokenized.sandbox.bka.sh/v1.2.0-beta' : 'https://tokenized.pay.bka.sh/v1.2.0-beta';

return [
    'username' => env('BKASH_USERNAME'),
    'password' => env('BKASH_PASSWORD'),
    'app_key' => env('BKASH_APP_KEY'),
    'app_secret' => env('BKASH_APP_SECRET'),
    'baseUrl' => $base_url,
    'websiteUrl' => env('APP_URL')
];
