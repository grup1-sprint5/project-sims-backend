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

    /*
    |--------------------------------------------------------------------------
    | IA Chat (Groq - OpenAI compatible endpoint)
    |--------------------------------------------------------------------------
    */
    'ia' => [
        'url'   => env('IA_API_URL', 'https://api.groq.com/openai/v1'),
        'key'   => env('IA_API_KEY'),
        'model' => env('IA_MODEL', 'llama-3.3-70b-versatile'),
    ],

    /*
    |--------------------------------------------------------------------------
    | IoT Microservice (FastAPI)
    |--------------------------------------------------------------------------
    |
    | Laravel acts as the bridge: frontend -> Laravel -> IoT microservice.
    */
    'iot' => [
        'url' => env('IOT_MICROSERVICE_URL', env('IOT_API_URL', 'http://host.docker.internal:8002')),
        'timeout' => (int) env('IOT_TIMEOUT', 3),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'success_url' => env('STRIPE_SUCCESS_URL'),
        'cancel_url' => env('STRIPE_CANCEL_URL'),
    ],

];
