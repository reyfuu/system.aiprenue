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

    // Agen Hermes (VPS) → POST /api/hermes/daily-report.
    // Kosong = endpointnya menolak semua permintaan.
    'hermes_agent' => [
        'token' => env('HERMES_AGENT_TOKEN'),
    ],

    // 9router — proxy LLM lokal di VPS (127.0.0.1). Dipakai untuk generate
    // OKR via ChatGPT & Claude. URL wajib penuh (http://host:port/v1).
    // token = API key 9router; chatgpt_model = model ChatGPT dengan prefix
    // cx/ (contoh: cx/gpt-5.6-sol); claude_model = model Claude dengan
    // prefix cc/ (contoh: cc/claude-opus-4-8).
    '9router' => [
        'url'             => env('NINEROUTER_URL'),
        'token'           => env('NINEROUTER_TOKEN'),
        'chatgpt_model'   => env('NINEROUTER_CHATGPT_MODEL', 'cx/gpt-5.6-sol'),
        'claude_model'    => env('NINEROUTER_CLAUDE_MODEL', 'cc/claude-opus-4-8'),
        'timeout'         => (int) env('NINEROUTER_TIMEOUT', 120),
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
