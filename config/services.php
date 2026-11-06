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

    /*
     * Optional fixed BTC price in US dollars (e.g. 76728.00) for the one-time
     * BTC → USDT balance conversion. When empty, the live price is used.
     */
    'market_prices' => [
        'btc_usd_conversion_price' => env('BTC_USD_CONVERSION_PRICE'),
    ],

    /*
     * A read-only Bybit API key, used to show the live balance of the company
     * Bybit account and check deposits against Bybit's deposit history.
     * Never give this key trade, transfer or withdrawal permission.
     */
    'bybit' => [
        'key' => env('BYBIT_API_KEY'),
        'secret' => env('BYBIT_API_SECRET'),
        'base_url' => env('BYBIT_API_URL', 'https://api.bybit.com'),
    ],

    /*
     * Daily database backups (app:backup-database). Set BACKUP_DISK to a
     * cloud disk such as "s3" to keep copies away from this server.
     */
    'backups' => [
        'disk' => env('BACKUP_DISK', 'local'),
        'keep' => (int) env('BACKUP_KEEP', 14),
    ],

];
