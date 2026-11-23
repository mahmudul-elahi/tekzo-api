<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'tenant_slug' => env('E_COMMERCE_TYPE', 'salamat-innovation'),
    'front_base_url' => env('FRONT_BASE_URL', 'https://salamatinnovation.com'),

    'reve_sms' => [
        'api_key' => env('REVE_SMS_API_KEY'),
        'secret_key' => env('REVE_SMS_SECRET_KEY'),
        'sender_id' => env('REVE_SMS_SENDER_ID'),
        'endpoint' => env('REVE_SMS_ENDPOINT', 'https://smpp.revesms.com:7790/sendtext'),
    ],

    'invoice' => [
        'download_logo_url' => env('INVOICE_DOWNLOAD_LOGO_URL', 'https://d2c2zgda4zwbg9.cloudfront.net/481/TEKZO-Logo-1000x1000.png'),
        'confirm_logo_url' => env('INVOICE_CONFIRM_LOGO_URL', 'https://salamat-innovation.s3.ap-south-1.amazonaws.com/575/TEKZO-Logo-1000x500.png'),
    ],

];
