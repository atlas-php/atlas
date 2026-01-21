<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Providers Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your AI providers here. Each provider requires specific
    | configuration such as API keys and base URLs. You can add as many
    | providers as you need.
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
            'version' => env('ANTHROPIC_API_VERSION', '2023-06-01'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Chat Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the default settings for chat completions.
    | These are the defaults used when no specific provider/model is specified.
    |
    */

    'chat' => [
        'provider' => env('ATLAS_CHAT_PROVIDER', 'openai'),
        'model' => env('ATLAS_CHAT_MODEL', 'gpt-4o'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Embedding Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the default settings for text embedding generation.
    | These settings control which provider, model, and parameters
    | are used when generating embeddings.
    |
    */

    'embedding' => [
        'provider' => env('ATLAS_EMBEDDING_PROVIDER', 'openai'),
        'model' => env('ATLAS_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'dimensions' => (int) env('ATLAS_EMBEDDING_DIMENSIONS', 1536),
        'batch_size' => (int) env('ATLAS_EMBEDDING_BATCH_SIZE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the default settings for image generation.
    | Consumers can override these through the fluent API.
    |
    */

    'image' => [
        'provider' => env('ATLAS_IMAGE_PROVIDER', 'openai'),
        'model' => env('ATLAS_IMAGE_MODEL', 'dall-e-3'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Speech Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the default settings for text-to-speech and speech-to-text
    | operations. Consumers can override these through the fluent API.
    |
    */

    'speech' => [
        'provider' => env('ATLAS_SPEECH_PROVIDER', 'openai'),
        'model' => env('ATLAS_SPEECH_MODEL', 'tts-1'),
        'transcription_model' => env('ATLAS_TRANSCRIPTION_MODEL', 'whisper-1'),
    ],

];
