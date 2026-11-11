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

    'imap' => [
        'user' => env('IMAP_USER'),
        'pass' => env('IMAP_PASS'),
    ],

    'woocommerce' => [
        'url' => env('WOOCOMMERCE_URL', 'https://copifam.com'),
        'ck' => env('WOOCOMMERCE_CK', 'ck_cf4c4d5cdefee48f2dd66d8e791c88d9ad7187a5'),
        'cs' => env('WOOCOMMERCE_CS', 'cs_1b4bb1a1ecd24aee785814c20f1fce6792216cbb'),
    ],

];
