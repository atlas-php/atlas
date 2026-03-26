<?php

declare(strict_types=1);

return [

    'defaults' => [
        'text' => ['provider' => env('ATLAS_TEXT_PROVIDER', 'openai'), 'model' => env('ATLAS_TEXT_MODEL', 'gpt-5.2')],
        'image' => ['provider' => env('ATLAS_IMAGE_PROVIDER', 'xai'), 'model' => env('ATLAS_IMAGE_MODEL', 'grok-imagine-image')],
        'video' => ['provider' => env('ATLAS_VIDEO_PROVIDER', 'xai'), 'model' => env('ATLAS_VIDEO_MODEL', 'grok-imagine-video')],
        'embed' => ['provider' => env('ATLAS_EMBED_PROVIDER', 'openai'), 'model' => env('ATLAS_EMBED_MODEL', 'text-embedding-3-small')],
        'moderate' => ['provider' => env('ATLAS_MODERATE_PROVIDER', 'openai'), 'model' => env('ATLAS_MODERATE_MODEL', 'omni-moderation-latest')],
        'rerank' => ['provider' => env('ATLAS_RERANK_PROVIDER'), 'model' => env('ATLAS_RERANK_MODEL')],
        'voice' => ['provider' => env('ATLAS_VOICE_PROVIDER', 'xai'), 'model' => env('ATLAS_VOICE_MODEL', 'grok-3-fast-realtime')],
    ],

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

        'lmstudio' => [
            'driver' => 'chat_completions',
            'api_key' => env('LMSTUDIO_API_KEY', 'lm-studio'),
            'base_url' => env('LMSTUDIO_URL', 'http://localhost:1234/v1'),
        ],

    ],

    'retry' => [
        'timeout' => (int) env('ATLAS_TIMEOUT', 60),
        'rate_limit' => (int) env('ATLAS_RETRY_RATE_LIMIT', 3),
        'errors' => (int) env('ATLAS_RETRY_ERRORS', 2),
    ],

    'queue' => env('ATLAS_QUEUE', 'default'),

    'stream' => [
        'chunk_delay_us' => (int) env('ATLAS_STREAM_CHUNK_DELAY_US', 15_000),
    ],

    'middleware' => [
        //
    ],

    'variables' => [
        'APP_NAME' => env('APP_NAME', 'Atlas Sandbox'),
    ],

    'storage' => [
        'disk' => env('ATLAS_STORAGE_DISK'),
        'prefix' => 'atlas',
    ],

    'embeddings' => [
        'dimensions' => (int) env('ATLAS_EMBEDDING_DIMENSIONS', 1536),
    ],

    'cache' => [
        'store' => env('ATLAS_CACHE_STORE'),
        'prefix' => 'atlas',
        'ttl' => [
            'models' => (int) env('ATLAS_CACHE_MODELS_TTL', 86400),
            'voices' => (int) env('ATLAS_CACHE_VOICES_TTL', 3600),
            'embeddings' => (int) env('ATLAS_CACHE_EMBEDDINGS_TTL', 0),
        ],
    ],

    'persistence' => [
        'enabled' => env('ATLAS_PERSISTENCE_ENABLED', true),
        'table_prefix' => env('ATLAS_TABLE_PREFIX', 'atlas_'),
        'message_limit' => (int) env('ATLAS_MESSAGE_LIMIT', 50),
        'auto_store_assets' => env('ATLAS_AUTO_STORE_ASSETS', true),
        'voice_route_prefix' => 'atlas',
        'voice_session_ttl' => (int) env('ATLAS_VOICE_SESSION_TTL', 60),
    ],

    'agents' => [
        'path' => __DIR__.'/../app/Agents',
        'namespace' => 'App\\Agents',
    ],

];
