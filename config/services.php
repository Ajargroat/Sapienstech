<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Supabase question bank
    |--------------------------------------------------------------------------
    |
    | The shared question bank lives in a Supabase (Postgres + Storage) project
    | and is pulled into the local `questions` pool by `php artisan bank:import`
    | (see App\Console\Commands\ImportQuestionBank). These three values are the
    | only thing a deployment must set:
    |
    |   SUPABASE_URL               the project URL, https://<ref>.supabase.co
    |   SUPABASE_SERVICE_ROLE_KEY  the service_role key (server-side secret)
    |   SUPABASE_BUCKET            the Storage bucket holding the page images
    |
    | `service_role_key` accepts either name (SUPABASE_ROLE_KEY wins when both
    | are present) so older .env files keep working. `verify` exists because
    | some Windows/PHP builds have no CA bundle, where every REST call dies with
    | "cURL error 60"; set SUPABASE_VERIFY=false there to fall back to the
    | system store. Never commit a real key — .env is git-ignored.
    */
    'supabase' => [
        'url' => env('SUPABASE_URL'),
        'service_role_key' => env('SUPABASE_ROLE_KEY', env('SUPABASE_SERVICE_ROLE_KEY')),
        'bucket' => env('SUPABASE_BUCKET', 'konkour-pages'),
        'verify' => filter_var(env('SUPABASE_VERIFY', true), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI provider (schedule assistant)
    |--------------------------------------------------------------------------
    |
    | Secrets and transport only. The *model* deliberately does NOT live here:
    | it is tenant-resolved config (`theme.ai.model`, see config/theme.php) so
    | one deploy serves every tenant and a tenant can be moved to a different
    | model without touching .env. This file holds the two things a tenant
    | must never be able to change: the endpoint and the credentials.
    |
    | Anything speaking the OpenAI /chat/completions shape works: OpenAI, Groq,
    | OpenRouter, DeepSeek, Together, a local vLLM/Ollama. Point AI_BASE_URL at
    | it and the client needs no code change.
    |
    | `keys` is a LIST so a deployment can rotate a key, or run a primary and a
    | fallback account, without a code change:
    |     AI_API_KEYS="sk-primary,sk-backup"
    |
    | Never echo these values: App\Support\Ai\OpenAiClient is the only reader,
    | and provider error bodies are never forwarded to the browser (they can
    | quote the key back on a malformed request).
    */
    'ai' => [
        'base_url' => env('AI_BASE_URL', 'https://api.openai.com/v1'),
        'keys' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('AI_API_KEYS', ''))
        ))),
        'timeout' => (int) env('AI_TIMEOUT', 30),
        'connect_timeout' => (int) env('AI_CONNECT_TIMEOUT', 8),
        'health_ttl' => (int) env('AI_HEALTH_TTL', 60),
        'stream' => filter_var(env('AI_STREAM', true), FILTER_VALIDATE_BOOL),
    ],
];
