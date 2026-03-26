<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    |
    | Default provider and model used when no arguments are passed to an entry
    | point. If a modality has no default configured, provider and model must
    | be passed explicitly on every call.
    |
    | Atlas::text()->message('Hello')->asText();         // uses text defaults
    | Atlas::text('anthropic', 'claude-opus-4-5')->...   // explicit override
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
        'voice' => ['provider' => env('ATLAS_VOICE_PROVIDER'), 'model' => env('ATLAS_VOICE_MODEL')],
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
    | Providers
    |--------------------------------------------------------------------------
    |
    | Credentials and endpoints for each AI provider. Only providers with an
    | api_key set will be registered and available for use. Remove or comment
    | out any provider you are not using.
    |
    */

    'providers' => [

        /*
         * OpenAI — https://platform.openai.com
         * Models: gpt-4o, gpt-4o-mini, o3, o4-mini, dall-e-3, tts-1, whisper-1, ...
         */
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
            'organization' => env('OPENAI_ORGANIZATION'),
        ],

        /*
         * Anthropic — https://console.anthropic.com
         * Models: claude-opus-4-5, claude-sonnet-4-5, claude-haiku-4-5, ...
         */
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
            'version' => env('ANTHROPIC_VERSION', '2024-10-22'),
        ],

        /*
         * Google — https://aistudio.google.com
         * Models: gemini-2.5-pro, gemini-2.0-flash, text-embedding-004, ...
         */
        'google' => [
            'api_key' => env('GOOGLE_API_KEY'),
            'url' => env('GOOGLE_URL', 'https://generativelanguage.googleapis.com'),
        ],

        /*
         * xAI — https://console.x.ai
         * Models: grok-3, grok-3-mini, grok-imagine-image, ...
         */
        'xai' => [
            'api_key' => env('XAI_API_KEY'),
            'url' => env('XAI_URL', 'https://api.x.ai/v1'),
        ],

        /*
         * Cohere — https://dashboard.cohere.com
         * Models: command-r-plus, embed-english-v3.0, rerank-v3.5, ...
         */
        'cohere' => [
            'api_key' => env('COHERE_API_KEY'),
            'url' => env('COHERE_URL', 'https://api.cohere.com'),
        ],

        /*
         * Jina — https://jina.ai
         * Models: jina-embeddings-v3, jina-reranker-v2-base-multilingual, ...
         */
        'jina' => [
            'api_key' => env('JINA_API_KEY'),
            'url' => env('JINA_URL', 'https://api.jina.ai'),
        ],

        /*
         * ElevenLabs — https://elevenlabs.io
         * Models: eleven_multilingual_v2, eleven_turbo_v2_5, ...
         */
        'elevenlabs' => [
            'api_key' => env('ELEVENLABS_API_KEY'),
            'url' => env('ELEVENLABS_URL', 'https://api.elevenlabs.io/v1'),
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

    ],

    /*
    |--------------------------------------------------------------------------
    | Retry
    |--------------------------------------------------------------------------
    |
    | Controls how Atlas behaves when a provider call fails or is slow.
    |
    | timeout    — Seconds before a single attempt is abandoned.
    | rate_limit — Retries on 429 (Too Many Requests). Waits for Retry-After.
    | errors     — Retries on 5xx / connection timeouts. Exponential backoff.
    |
    | Permanent failures (401, 403) are never retried.
    |
    | Override per call:
    |   ->withTimeout(120)
    |   ->withRetry(rateLimit: 5, errors: 3)
    |   ->withoutRetry()
    |
    */

    'retry' => [
        'timeout' => (int) env('ATLAS_TIMEOUT', 60),
        'rate_limit' => (int) env('ATLAS_RETRY_RATE_LIMIT', 3),
        'errors' => (int) env('ATLAS_RETRY_ERRORS', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | The default queue that async Atlas jobs are dispatched onto. Any call
    | using ->queue() will use this queue unless overridden per call.
    |
    | Worker configuration (retry attempts, backoff, job timeout) belongs
    | in config/horizon.php or your queue supervisor, not here.
    |
    | Override per call:
    |   ->onQueue('ai')
    |   ->onConnection('redis')
    |   ->withDelay(30)
    |
    */

    'queue' => env('ATLAS_QUEUE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Streaming
    |--------------------------------------------------------------------------
    |
    | The chunk delay adds a small pause between text chunks when converting
    | tool-loop results to a stream, creating a visible typing effect for
    | broadcast consumers. Set to 0 in tests or CLI.
    |
    */

    'stream' => [
        'chunk_delay_us' => (int) env('ATLAS_STREAM_CHUNK_DELAY_US', 15_000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Global middleware stacks applied to every call at each layer.
    |
    | agent    — Wraps the entire agent execution.
    | step     — Wraps each round trip in the tool call loop.
    | tool     — Wraps each individual tool execution.
    | provider — Wraps every HTTP call to any provider.
    |
    */

    'middleware' => [
        'agent' => [],
        'step' => [],
        'tool' => [],
        'provider' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Variables
    |--------------------------------------------------------------------------
    |
    | Static values available in all instructions using {VARIABLE} syntax.
    | These are lowest priority — runtime registry and ->withVariables()
    | take precedence.
    |
    */

    'variables' => [
        'APP_NAME' => env('APP_NAME', 'Laravel'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Which Laravel disk to use when storing media (images, audio, video)
    | via ->store() methods. Falls back to your default filesystem disk.
    | The prefix is prepended to auto-generated filenames.
    |
    */

    'storage' => [
        'disk' => env('ATLAS_STORAGE_DISK'),
        'prefix' => 'atlas',
    ],

    /*
    |--------------------------------------------------------------------------
    | Embeddings
    |--------------------------------------------------------------------------
    |
    | Vector column size for pgvector migrations. Must match the output
    | dimensions of your embedding model (OpenAI text-embedding-3-small = 1536).
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
    | Caching for provider data (models, voices) and embeddings.
    | Set any TTL to 0 to disable caching for that type.
    |
    */

    'cache' => [
        'store' => env('ATLAS_CACHE_STORE'),
        'prefix' => 'atlas',
        'ttl' => [
            'models' => (int) env('ATLAS_CACHE_MODELS_TTL', 86400),
            'voices' => (int) env('ATLAS_CACHE_VOICES_TTL', 3600),
            'embeddings' => (int) env('ATLAS_CACHE_EMBEDDINGS_TTL', 0),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Persistence
    |--------------------------------------------------------------------------
    |
    | Optional conversation persistence and execution tracking. Atlas works
    | fully stateless when disabled. When enabled, execution tracking is
    | always active — conversations with tools require execution data.
    |
    */

    'persistence' => [
        'enabled' => env('ATLAS_PERSISTENCE_ENABLED', false),
        'table_prefix' => env('ATLAS_TABLE_PREFIX', 'atlas_'),
        'message_limit' => (int) env('ATLAS_MESSAGE_LIMIT', 50),
        'auto_store_assets' => env('ATLAS_AUTO_STORE_ASSETS', true),

        'voice_transcripts' => [
            'middleware' => [],
            'route_prefix' => 'atlas',
        ],

        'voice_session_ttl' => (int) env('ATLAS_VOICE_SESSION_TTL', 60),

        'models' => [
            // 'conversation'               => \App\Models\AiConversation::class,
            // 'conversation_message'        => \App\Models\AiMessage::class,
            // 'asset'                       => \App\Models\AiAsset::class,
            // 'conversation_message_asset'  => \App\Models\AiMessageAttachment::class,
            // 'execution'                   => \App\Models\AiExecution::class,
            // 'execution_step'              => \App\Models\AiExecutionStep::class,
            // 'execution_tool_call'         => \App\Models\AiExecutionToolCall::class,
        ],
    ],

];
