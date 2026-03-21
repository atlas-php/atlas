<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Base test case for persistence tests.
 *
 * Loads persistence migrations, enables persistence config,
 * and provides a clean database per test via RefreshDatabase.
 *
 * When DB_CONNECTION is set to 'pgsql' (e.g. in CI), the PostgreSQL
 * connection is used. Otherwise falls back to in-memory SQLite.
 */
abstract class PersistenceTestCase extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('atlas.persistence.enabled', true);
        $app['config']->set('atlas.persistence.table_prefix', 'atlas_');

        // Respect DB_CONNECTION from environment (e.g. CI PostgreSQL job).
        // Only set SQLite in-memory config when no external DB is configured.
        $dbConnection = env('DB_CONNECTION');

        if ($dbConnection === 'pgsql') {
            $app['config']->set('database.default', 'pgsql');
            $app['config']->set('database.connections.pgsql', [
                'driver' => 'pgsql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '5432'),
                'database' => env('DB_DATABASE', 'atlas_test'),
                'username' => env('DB_USERNAME', 'atlas'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8',
                'prefix' => '',
                'schema' => 'public',
            ]);
        } else {
            $app['config']->set('database.default', 'testing');
            $app['config']->set('database.connections.testing', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]);
        }
    }
}
