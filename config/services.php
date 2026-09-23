<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
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

    'asaas' => [
        'environment' => env('ASAAS_ENV', 'sandbox'),
        'base_url' => env(
            'ASAAS_API_URL',
            'https://api-sandbox.asaas.com/v3'
        ),
        'api_key' => env('ASAAS_API_KEY'),
        'checkout_url' => env(
            'ASAAS_CHECKOUT_URL',
            'https://sandbox.asaas.com/checkoutSession/show?id='
        ),
        'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
    ],

];
