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

    'default' => [
        'provider' => env('ATLAS_DEFAULT_PROVIDER', 'openai'),
        'model' => env('ATLAS_DEFAULT_MODEL', 'gpt-4o'),
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

];
