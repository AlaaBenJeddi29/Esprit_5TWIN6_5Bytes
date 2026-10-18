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
    /

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

    /
    |--------------------------------------------------------------------------
    | Gemini AI
    |--------------------------------------------------------------------------
    /

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 12),
        'ca_bundle' => env('GEMINI_CA_BUNDLE'),
    ],

    /
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
    | Local LLM layer (Ollama only — Gestion 5 and future modules)
    |--------------------------------------------------------------------------
    |
    | Zero-cost, reusable layer consumed by App\Services\LocalLlmService.
    | Inference goes ONLY to an Ollama installed on this machine: the host
    | must be localhost / 127.x / ::1 (enforced in code, no external call is
    | ever made). The application keeps working when Ollama is unavailable —
    | callers receive a predictable success/failure result. Defaults are
    | non-secret; see .env.example for the manual setup commands.
    |
    */

    'local_llm' => [
        'enabled' => (bool) env('LOCAL_LLM_ENABLED', true),
        // Falls back to the Gestion 1 Ollama URL when not set.
        'url' => env('LOCAL_LLM_URL', env('OLLAMA_URL', 'http://localhost:11434')),
        'model' => env('LOCAL_LLM_MODEL', 'llama3.2'),
        'timeout' => (int) env('LOCAL_LLM_TIMEOUT', 120),
        'connect_timeout' => (int) env('LOCAL_LLM_CONNECT_TIMEOUT', 5),
        'temperature' => (float) env('LOCAL_LLM_TEMPERATURE', 0.3),
    ],

];
