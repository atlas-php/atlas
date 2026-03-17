<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Pipelines Configuration
    |--------------------------------------------------------------------------
    |
    | Control whether Atlas pipelines are enabled. Pipelines provide
    | middleware hooks for observability, logging, and custom processing
    | during agent execution and API operations.
    |
    */

    'pipelines' => [
        'enabled' => env('ATLAS_PIPELINES_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Events Configuration
    |--------------------------------------------------------------------------
    |
    | Control whether Atlas dispatches Laravel events at lifecycle points.
    | Events are informational (observe-only) and fire AFTER pipelines.
    | Disable to eliminate event overhead when not using listeners.
    |
    */

    'events' => [
        'enabled' => env('ATLAS_EVENTS_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Agents Configuration (auto-discovery) *optional
    |--------------------------------------------------------------------------
    |
    | Configure where Atlas should look for agent definitions.
    | Agents can also be registered programmatically via the AgentRegistry.
    |
    */

    'agents' => [
        'path' => app_path('Agents'),
        'namespace' => 'App\\Agents',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tools Configuration (auto-discovery) *optional
    |--------------------------------------------------------------------------
    |
    | Configure where Atlas should look for tool definitions.
    | Tools can also be registered programmatically via the ToolRegistry.
    |
    */

    'tools' => [
        'path' => app_path('Tools'),
        'namespace' => 'App\\Tools',
    ],

    /*
    |--------------------------------------------------------------------------
    | Embeddings Configuration
    |--------------------------------------------------------------------------
    |
    | Configure default provider/model for embeddings and optional caching.
    | When provider and model are set, Atlas::embeddings() uses them
    | automatically. Users can still override with ->using().
    |
    */

    'embeddings' => [
        'provider' => env('ATLAS_EMBEDDING_PROVIDER'),
        'model' => env('ATLAS_EMBEDDING_MODEL'),
        'cache' => [
            'enabled' => env('ATLAS_EMBEDDING_CACHE_ENABLED', false),
            'store' => env('ATLAS_EMBEDDING_CACHE_STORE'),
            'ttl' => (int) env('ATLAS_EMBEDDING_CACHE_TTL', 3600),
        ],
    ],

];
