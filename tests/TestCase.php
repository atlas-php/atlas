<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tests;

use Atlasphp\Atlas\AtlasServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Prism\Prism\PrismServiceProvider;

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
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            PrismServiceProvider::class,
            AtlasServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('atlas.providers', [
            'openai' => [
                'api_key' => 'test-key',
                'url' => 'https://api.openai.com/v1',
            ],
            'anthropic' => [
                'api_key' => 'anthropic-key',
                'version' => '2023-06-01',
            ],
        ]);
        $app['config']->set('atlas.chat', [
            'provider' => 'openai',
            'model' => 'gpt-4o',
        ]);
        $app['config']->set('atlas.embedding', [
            'provider' => 'openai',
            'model' => 'text-embedding-3-small',
            'dimensions' => 1536,
            'batch_size' => 100,
        ]);
        $app['config']->set('atlas.image', [
            'provider' => 'openai',
            'model' => 'dall-e-3',
        ]);
        $app['config']->set('atlas.speech', [
            'provider' => 'openai',
            'model' => 'tts-1',
            'transcription_model' => 'whisper-1',
        ]);
    }
}
