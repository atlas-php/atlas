<?php

declare(strict_types=1);

return [

    'default' => env('CACHE_STORE', 'database'),

    'stores' => [

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CONNECTION', 'pgsql'),
            'table' => 'cache',
            'lock_connection' => env('DB_CONNECTION', 'pgsql'),
            'lock_table' => 'cache_locks',
        ],

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

    ],

    'prefix' => env('CACHE_PREFIX', 'atlas_sandbox_cache_'),

];
