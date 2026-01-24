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

];
