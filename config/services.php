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

    'money_forward' => [
        'client_id' => env('MONEY_FORWARD_CLIENT_ID'),
        'client_secret' => env('MONEY_FORWARD_CLIENT_SECRET'),
        'redirect_uri' => env('MONEY_FORWARD_REDIRECT_URI', 'http://localhost:8000/callback'),
        'product_sync_redirect_uri' => env('MONEY_FORWARD_PRODUCT_SYNC_REDIRECT_URI', 'http://localhost:8000/products/sync/callback'),
        'authorization_url' => 'https://api.biz.moneyforward.com/authorize',
        'token_url' => 'https://api.biz.moneyforward.com/token',
        'api_url' => 'https://invoice.moneyforward.com/api/v3',
        'billing_sync_throttle_minutes' => env('MONEY_FORWARD_BILLING_SYNC_THROTTLE_MINUTES', 5),
        'billing_sync_page_size' => env('MONEY_FORWARD_BILLING_SYNC_PAGE_SIZE', 100),
        'quote_sync_throttle_minutes' => env('MONEY_FORWARD_QUOTE_SYNC_THROTTLE_MINUTES', 5),
        'quote_sync_page_size' => env('MONEY_FORWARD_QUOTE_SYNC_PAGE_SIZE', 100),
        'quote_redirect_uri' => env('MONEY_FORWARD_QUOTE_REDIRECT_URI', 'http://localhost:8000/quotes/mf/auth/callback'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com'),
        'model' => env('OPENAI_NOTES_MODEL', 'gpt-4o-mini'),
    ],

    'google_chat' => [
        'approval_webhook' => env('GOOGLE_CHAT_APPROVAL_WEBHOOK'),
    ],

    'customer_portal' => [
        'base_url' => env('CUSTOMER_PORTAL_BASE_URL', 'https://pm.xerographix.co.jp/customers'),
    ],

];
