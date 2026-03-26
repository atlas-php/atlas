<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tests;

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\AtlasServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * Base test case for all Atlas package tests.
 *
 * Provides Orchestra Testbench integration for testing Laravel package functionality.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Get package providers.
     *
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            AtlasServiceProvider::class,
        ];
    }

    /**
     * Get package aliases.
     *
     * @param  Application  $app
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'Atlas' => Atlas::class,
        ];
    }
}
