<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Provider & Model
    |--------------------------------------------------------------------------
    |
    | The default provider and model used when none is explicitly specified.
    |
    */

    'defaults' => [
        'text' => ['provider' => env('ATLAS_TEXT_PROVIDER', 'anthropic'), 'model' => env('ATLAS_TEXT_MODEL', 'claude-sonnet-4-20250514')],
        'image' => ['provider' => env('ATLAS_IMAGE_PROVIDER', 'xai'), 'model' => env('ATLAS_IMAGE_MODEL', 'grok-2-image')],
        'video' => ['provider' => env('ATLAS_VIDEO_PROVIDER', 'xai'), 'model' => env('ATLAS_VIDEO_MODEL', 'grok-2-video')],
        'embed' => ['provider' => env('ATLAS_EMBED_PROVIDER', 'openai'), 'model' => env('ATLAS_EMBED_MODEL', 'text-embedding-3-small')],
        'moderate' => ['provider' => env('ATLAS_MODERATE_PROVIDER', 'openai'), 'model' => env('ATLAS_MODERATE_MODEL', 'omni-moderation-latest')],
        'rerank' => ['provider' => env('ATLAS_RERANK_PROVIDER'), 'model' => env('ATLAS_RERANK_MODEL')],
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Configurations
    |--------------------------------------------------------------------------
    |
    | Configuration for each AI provider. Each provider requires at minimum
    | an API key. Additional provider-specific options can be set here.
    |
    */

    'providers' => [

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
            'organization' => env('OPENAI_ORGANIZATION'),
        ],

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
            'version' => env('ANTHROPIC_VERSION', '2024-10-22'),
        ],

        'google' => [
            'api_key' => env('GOOGLE_API_KEY', env('GEMINI_API_KEY')),
            'url' => env('GOOGLE_URL', 'https://generativelanguage.googleapis.com'),
        ],

        'xai' => [
            'api_key' => env('XAI_API_KEY'),
            'url' => env('XAI_URL', 'https://api.x.ai/v1'),
        ],

        'cohere' => [
            'api_key' => env('COHERE_API_KEY'),
            'url' => env('COHERE_URL', 'https://api.cohere.com'),
        ],

        'jina' => [
            'api_key' => env('JINA_API_KEY'),
            'url' => env('JINA_URL', 'https://api.jina.ai'),
        ],

        // ─── Custom Providers (Chat Completions compatible) ─────────────

        'lmstudio' => [
            'driver' => 'chat_completions',
            'api_key' => env('LMSTUDIO_API_KEY', 'lm-studio'),
            'base_url' => env('LMSTUDIO_URL', 'http://localhost:1234/v1'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Timeout Configuration
    |--------------------------------------------------------------------------
    |
    | Request timeout values in seconds for different operation types.
    |
    */

    'timeout' => [
        'default' => (int) env('ATLAS_TIMEOUT', 60),
        'reasoning' => (int) env('ATLAS_TIMEOUT_REASONING', 300),
        'media' => (int) env('ATLAS_TIMEOUT_MEDIA', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Global middleware applied at each layer of Atlas execution.
    | Provider middleware runs on every HTTP call to an AI provider.
    | Step middleware runs on each executor round trip.
    | Tool middleware runs on each tool execution.
    | Agent middleware runs on each agent execution.
    |
    */

    'middleware' => [
        'provider' => [],
        'step' => [],
        'tool' => [],
        'agent' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Variables
    |--------------------------------------------------------------------------
    |
    | Static variables available in all instructions across all modalities.
    |
    */

    'variables' => [
        'APP_NAME' => env('APP_NAME', 'Atlas Sandbox'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Configuration for queued Atlas executions.
    |
    */

    'queue' => [
        'connection' => env('ATLAS_QUEUE_CONNECTION'),
        'queue' => env('ATLAS_QUEUE', 'default'),
        'tries' => (int) env('ATLAS_QUEUE_TRIES', 3),
        'backoff' => (int) env('ATLAS_QUEUE_BACKOFF', 30),
        'timeout' => (int) env('ATLAS_QUEUE_TIMEOUT', 300),
        'after_commit' => (bool) env('ATLAS_QUEUE_AFTER_COMMIT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Media Storage
    |--------------------------------------------------------------------------
    |
    | Configure how Atlas stores media files (images, audio, video).
    |
    */

    'storage' => [
        'disk' => env('ATLAS_STORAGE_DISK'),
        'prefix' => 'atlas',
        'visibility' => 'private',
    ],

    /*
    |--------------------------------------------------------------------------
    | Embeddings
    |--------------------------------------------------------------------------
    */

    'embeddings' => [
        'dimensions' => (int) env('ATLAS_EMBEDDING_DIMENSIONS', 1536),
    ],

    /*
    |--------------------------------------------------------------------------
    | Persistence
    |--------------------------------------------------------------------------
    */

    'persistence' => [
        'enabled' => env('ATLAS_PERSISTENCE_ENABLED', true),
        'table_prefix' => env('ATLAS_TABLE_PREFIX', 'atlas_'),
        'message_limit' => (int) env('ATLAS_MESSAGE_LIMIT', 50),
        'auto_store_assets' => env('ATLAS_AUTO_STORE_ASSETS', true),
        'memory_auto_embed' => env('ATLAS_MEMORY_AUTO_EMBED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Agents
    |--------------------------------------------------------------------------
    */

    'agents' => [
        'path' => __DIR__.'/../app/Agents',
        'namespace' => 'App\\Agents',
    ],

];
