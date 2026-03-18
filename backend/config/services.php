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

    'gemini' => [
        'default_model' => env('GEMINI_DEFAULT_MODEL', 'gemini-1.5-flash'),
    ],

    'ai' => [
        'pricing' => [
            'exchange_rate' => 5.0, // USD to BRL
            'models' => [
                'gemini-1.5-flash' => [
                    'input' => 0.075 / 1000000,
                    'output' => 0.30 / 1000000,
                ],
                'gemini-1.5-pro' => [
                    'input' => 3.50 / 1000000,
                    'output' => 10.50 / 1000000,
                ],
                'gemini-2.0-flash' => [
                    'input' => 0.10 / 1000000,
                    'output' => 0.40 / 1000000,
                ],
                'default' => [
                    'input' => 0.075 / 1000000,
                    'output' => 0.30 / 1000000,
                ],
            ],
        ],
    ],

];
