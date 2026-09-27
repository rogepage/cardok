<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'providers' => [
        'rest_url' => env('PROVIDER_REST_URL', 'http://provider-rest:8000'),
        'soap_url' => env('PROVIDER_SOAP_URL', 'http://provider-soap:8000'),
        'order' => explode(',', env('PROVIDER_ORDER', 'rest,soap')),
        'timeout' => (int) env('PROVIDER_TIMEOUT', 2),
        'retries' => (int) env('PROVIDER_RETRIES', 2),
    ],

    'payment' => [
        'url' => env('PAYMENT_PROVIDER_URL', 'http://payment-provider:8000'),
    ],

];
