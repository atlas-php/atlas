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
        'music' => ['provider' => env('ATLAS_MUSIC_PROVIDER'), 'model' => env('ATLAS_MUSIC_MODEL')],
        'sfx' => ['provider' => env('ATLAS_SFX_PROVIDER'), 'model' => env('ATLAS_SFX_MODEL')],
        'speech' => ['provider' => env('ATLAS_SPEECH_PROVIDER'), 'model' => env('ATLAS_SPEECH_MODEL')],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agents
    |--------------------------------------------------------------------------
    |
    | Auto-discovery path and namespace for agent classes. Agents found in
    | the configured directory are automatically registered at boot time.
    |
    */

    'agents' => [
        'path' => null,
        'namespace' => null,
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

        'elevenlabs' => [
            'api_key' => env('ELEVENLABS_API_KEY'),
            'url' => env('ELEVENLABS_URL', 'https://api.elevenlabs.io/v1'),
            'media_timeout' => 300,
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

        // ─── Custom Provider (own driver class) ─────────────────────────
        //
        // Use a custom driver class for non-OpenAI-compatible providers.
        // The class receives all driver dependencies (ProviderConfig,
        // HttpClient, MiddlewareStack, AtlasCache) via constructor injection.
        //
        // 'my-provider' => [
        //     'driver'       => \App\Atlas\MyCustomDriver::class,
        //     'api_key'      => env('MY_PROVIDER_API_KEY'),
        //     'base_url'     => 'https://api.my-provider.com/v1',
        //     'capabilities' => ['text' => true, 'stream' => true],
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
    | Variables
    |--------------------------------------------------------------------------
    |
    | Static variables available in all instructions across all modalities.
    | These are the lowest priority — global registry and withVariables()
    | override them.
    |
    | Supports flat keys and nested arrays:
    |   'APP_NAME' => 'My App'
    |   'COMPANY' => ['NAME' => 'Acme', 'SUPPORT_EMAIL' => 'help@acme.com']
    |
    | Access flat: {APP_NAME}
    | Access nested: {COMPANY.NAME}, {COMPANY.SUPPORT_EMAIL}
    |
    */

    'variables' => [
        'APP_NAME' => env('APP_NAME', 'Laravel'),
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
    | Embeddings
    |--------------------------------------------------------------------------
    |
    | Configuration for embedding generation. The dimensions value controls
    | vector column size in migrations. For embedding caching, see the
    | 'cache' section below (atlas.cache.ttl.embeddings).
    |
    | Note: 'dimensions' was previously at 'persistence.embedding_dimensions'.
    | The old location is still supported as a fallback in migrations.
    |
    */

    'embeddings' => [
        'dimensions' => (int) env('ATLAS_EMBEDDING_DIMENSIONS', 1536),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Unified caching for provider data (models, voices) and embeddings.
    | Set any TTL to 0 to disable caching for that type.
    |
    */

    'cache' => [
        'store' => env('ATLAS_CACHE_STORE'),
        'prefix' => 'atlas',
        'ttl' => [
            'models' => (int) env('ATLAS_CACHE_MODELS_TTL', 86400),        // 24 hours
            'voices' => (int) env('ATLAS_CACHE_VOICES_TTL', 3600),         // 1 hour
            'embeddings' => (int) env('ATLAS_CACHE_EMBEDDINGS_TTL', 0),    // disabled by default
        ],
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
        'message_limit' => (int) env('ATLAS_MESSAGE_LIMIT', 50),

        // Auto-store generated files (images, audio, video) as assets.
        // Only applies to direct calls tracked by TrackProviderCall.
        // Tool-generated assets use ToolAssets::store() explicitly.
        'auto_store_assets' => env('ATLAS_AUTO_STORE_ASSETS', true),

        // Auto-embed memories on create/update via HasVectorEmbeddings.
        // Disable if you want to embed asynchronously via a job.
        'memory_auto_embed' => env('ATLAS_MEMORY_AUTO_EMBED', true),

        // Model overrides — extend base models with your own
        'models' => [
            // 'conversation'        => \Atlasphp\Atlas\Persistence\Models\Conversation::class,
            // 'message'             => \Atlasphp\Atlas\Persistence\Models\Message::class,
            // 'asset'               => \Atlasphp\Atlas\Persistence\Models\Asset::class,
            // 'message_attachment'   => \Atlasphp\Atlas\Persistence\Models\MessageAttachment::class,
            // 'execution'           => \Atlasphp\Atlas\Persistence\Models\Execution::class,
            // 'execution_step'      => \Atlasphp\Atlas\Persistence\Models\ExecutionStep::class,
            // 'execution_tool_call' => \Atlasphp\Atlas\Persistence\Models\ExecutionToolCall::class,
            // 'memory'              => \Atlasphp\Atlas\Persistence\Models\Memory::class,
        ],
    ],

];
