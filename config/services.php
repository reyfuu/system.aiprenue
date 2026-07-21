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

    // Agen Daily Script Rave (repo privat, GitHub Actions) → POST /api/scripts.
    // Kosong = endpointnya menolak semua permintaan; jangan taruh nilainya di sini.
    'script_agent' => [
        'token' => env('SCRIPT_AGENT_TOKEN'),
    ],

    // Agen Insight (cron di VPS) → POST /api/insights.
    // Kosong = endpointnya menolak semua permintaan; jangan taruh nilainya di sini.
    'insight_agent' => [
        'token' => env('INSIGHT_AGENT_TOKEN'),
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

];
