<?php

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
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => 'auth/google/callback'
    ],
    'ipay88' => [
        'key' => env('MERCHANT_KEY'),
        'code' => env('MERCHANT_CODE'),
        'entry_url' => env('IPAY88_ENTRY_URL', 'https://payment.ipay88.com.my/epayment/entry.asp'),
    ],
    'delyva' => [
        'base_url' => env('DELYVA_BASE_URL', 'https://api.delyva.app/v1.0'),
        'api_key' => env('DELYVA_API_KEY'),
        'customer_id' => env('DELYVA_CUSTOMER_ID'),
        'company_id' => env('DELYVA_COMPANY_ID'),
        'item_type' => env('DELYVA_ITEM_TYPE', 'PARCEL'),
        'source' => env('DELYVA_SOURCE', 'bonbon'),
    ],
    'monday' => [
        'token' => env('MONDAY_TOKEN'),
    ],
];
