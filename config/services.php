<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third-party services such
    | as Mailgun, Postmark, AWS, and more. This file provides the conventional
    | location for service credentials.
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
    | Gemini AI
    |--------------------------------------------------------------------------
    */

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 12),
        'ca_bundle' => env('GEMINI_CA_BUNDLE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ollama (Local AI — Gestion 1 Reports)
    |--------------------------------------------------------------------------
    |
    | Ollama runs locally on the manager's computer. The URL and model
    | are configurable through .env to accommodate different computers.
    |
    */

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        'model' => env('OLLAMA_MODEL', 'llama3.2'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Confirmation IA des incidents similaires
    |--------------------------------------------------------------------------
    |
    | Fournisseur compatible OpenAI (/chat/completions). Désactivé par défaut :
    | la détection reste entièrement locale tant que XAI_API_KEY est absente.
    | L'URL et le modèle sont configurables pour rester indépendant du
    | fournisseur (xAI, Groq, etc.).
    |
    */

    'xai' => [
        'api_key' => env('XAI_API_KEY'),
        'url' => env('XAI_API_URL', 'https://api.x.ai/v1/chat/completions'),
        'model' => env('XAI_MODEL', 'grok-4.7'),
        'timeout' => (int) env('XAI_TIMEOUT', 15),
    ],

];
