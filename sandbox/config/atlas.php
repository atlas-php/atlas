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
    | Agents Configuration (auto-discovery)
    |--------------------------------------------------------------------------
    |
    | Configure where Atlas should look for agent definitions.
    | Sandbox uses app/Agents/ for auto-discovery of agent classes.
    |
    */

    'agents' => [
        'path' => app_path('Agents'),
        'namespace' => 'App\\Agents',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tools Configuration (auto-discovery)
    |--------------------------------------------------------------------------
    |
    | Configure where Atlas should look for tool definitions.
    | Sandbox uses app/Tools/ for auto-discovery of tool classes.
    |
    */

    'tools' => [
        'path' => app_path('Tools'),
        'namespace' => 'App\\Tools',
    ],

];
