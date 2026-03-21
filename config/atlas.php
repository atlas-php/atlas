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
        'text' => ['provider' => env('ATLAS_TEXT_PROVIDER'), 'model' => env('ATLAS_TEXT_MODEL')],
        'image' => ['provider' => env('ATLAS_IMAGE_PROVIDER'), 'model' => env('ATLAS_IMAGE_MODEL')],
        'video' => ['provider' => env('ATLAS_VIDEO_PROVIDER'), 'model' => env('ATLAS_VIDEO_MODEL')],
        'embed' => ['provider' => env('ATLAS_EMBED_PROVIDER'), 'model' => env('ATLAS_EMBED_MODEL')],
        'moderate' => ['provider' => env('ATLAS_MODERATE_PROVIDER'), 'model' => env('ATLAS_MODERATE_MODEL')],
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
            'api_key' => env('GOOGLE_API_KEY'),
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
        //
        // Add a 'driver' key to use a named driver or custom class.
        // Available named drivers: 'chat_completions', 'responses'
        //
        // 'ollama' => [
        //     'driver'   => 'chat_completions',
        //     'api_key'  => env('OLLAMA_API_KEY', 'ollama'),
        //     'base_url' => env('OLLAMA_URL', 'http://localhost:11434/v1'),
        // ],
        //
        // 'lmstudio' => [
        //     'driver'   => 'chat_completions',
        //     'api_key'  => env('LMSTUDIO_API_KEY', 'lm-studio'),
        //     'base_url' => env('LMSTUDIO_URL', 'http://localhost:1234/v1'),
        // ],
        //
        // 'groq' => [
        //     'driver'   => 'chat_completions',
        //     'api_key'  => env('GROQ_API_KEY'),
        //     'base_url' => 'https://api.groq.com/openai/v1',
        // ],
        //
        // 'together' => [
        //     'driver'   => 'chat_completions',
        //     'api_key'  => env('TOGETHER_API_KEY'),
        //     'base_url' => 'https://api.together.xyz/v1',
        // ],
        //
        // 'deepseek' => [
        //     'driver'   => 'chat_completions',
        //     'api_key'  => env('DEEPSEEK_API_KEY'),
        //     'base_url' => 'https://api.deepseek.com/v1',
        // ],
        //
        // 'openrouter' => [
        //     'driver'   => 'chat_completions',
        //     'api_key'  => env('OPENROUTER_API_KEY'),
        //     'base_url' => 'https://openrouter.ai/api/v1',
        // ],

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
    | Queue
    |--------------------------------------------------------------------------
    |
    | Configuration for queued Atlas executions. Use ->queue() on any
    | pending request to dispatch it asynchronously.
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
    | Used by Input::store() and Response::store() methods.
    |
    */

    'storage' => [
        'disk' => env('ATLAS_STORAGE_DISK'),
        'prefix' => 'atlas',
        'visibility' => 'private',
    ],

    /*
    |--------------------------------------------------------------------------
    | Persistence
    |--------------------------------------------------------------------------
    |
    | Optional conversation persistence and execution tracking. If you don't
    | publish the migrations, Atlas works fully stateless. When enabled,
    | execution tracking is always active — conversations with tools require
    | execution data for replay.
    |
    */

    'persistence' => [
        'enabled' => env('ATLAS_PERSISTENCE_ENABLED', false),
        'table_prefix' => env('ATLAS_TABLE_PREFIX', 'atlas_'),
        'embedding_dimensions' => (int) env('ATLAS_EMBEDDING_DIMENSIONS', 1536),
        'message_limit' => (int) env('ATLAS_MESSAGE_LIMIT', 50),

        // Auto-store generated files (images, audio, video) as assets.
        // Only applies to direct calls tracked by TrackProviderCall.
        // Tool-generated assets use ToolAssets::store() explicitly.
        'auto_store_assets' => env('ATLAS_AUTO_STORE_ASSETS', true),

        // Model overrides — extend base models with your own
        'models' => [
            // 'conversation'        => \Atlasphp\Atlas\Persistence\Models\Conversation::class,
            // 'message'             => \Atlasphp\Atlas\Persistence\Models\Message::class,
            // 'asset'               => \Atlasphp\Atlas\Persistence\Models\Asset::class,
            // 'message_attachment'   => \Atlasphp\Atlas\Persistence\Models\MessageAttachment::class,
            // 'execution'           => \Atlasphp\Atlas\Persistence\Models\Execution::class,
            // 'execution_step'      => \Atlasphp\Atlas\Persistence\Models\ExecutionStep::class,
            // 'execution_tool_call' => \Atlasphp\Atlas\Persistence\Models\ExecutionToolCall::class,
        ],
    ],

];
