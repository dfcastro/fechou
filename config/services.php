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


    'payment' => [
        'provider' => env(
            'PAYMENT_PROVIDER',
            'stripe'
        ),
    ],

    'stripe' => [
        'secret' => env(
            'STRIPE_SECRET'
        ),

        'price_pro' => env(
            'STRIPE_PRICE_PRO'
        ),

        'webhook_secret' => env(
            'STRIPE_WEBHOOK_SECRET'
        ),
    ],


    'mercadopago' => [
        'environment' => env(
            'MERCADOPAGO_ENV',
            'test'
        ),

        'base_url' => env(
            'MERCADOPAGO_API_URL',
            'https://api.mercadopago.com'
        ),

        'access_token' => env(
            'MERCADOPAGO_ACCESS_TOKEN'
        ),

        'public_key' => env(
            'MERCADOPAGO_PUBLIC_KEY'
        ),

        'webhook_secret' => env(
            'MERCADOPAGO_WEBHOOK_SECRET'
        ),

        'subscription_plan_pro' => env(
            'MERCADOPAGO_SUBSCRIPTION_PLAN_PRO'
        ),
    ],

];
