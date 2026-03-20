<?php

declare(strict_types=1);

namespace Atlasphp\Atlas;

use Atlasphp\Atlas\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Middleware\MiddlewareStack;
use Atlasphp\Atlas\Providers\Cohere\CohereDriver;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\Jina\JinaDriver;
use Atlasphp\Atlas\Providers\OpenAi\OpenAiDriver;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\ProviderRegistry;
use Atlasphp\Atlas\Providers\Xai\XaiDriver;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Atlas package.
 *
 * Registers the provider registry, manager, and HTTP client as singletons,
 * merges configuration, and publishes the config file.
 */
class AtlasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/atlas.php', 'atlas');

        $this->app->singleton(ProviderRegistryContract::class, function ($app) {
            return new ProviderRegistry($app);
        });

        $this->app->singleton(AtlasManager::class, function ($app) {
            return new AtlasManager($app->make(ProviderRegistryContract::class));
        });

        $this->app->singleton(HttpClient::class, function ($app) {
            return new HttpClient($app->make(Dispatcher::class));
        });

        $this->app->singleton(MiddlewareStack::class, function ($app) {
            return new MiddlewareStack($app);
        });
    }

    public function boot(): void
    {
        $this->registerProviders();

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\MakeAgentCommand::class,
                Console\MakeToolCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/atlas.php' => config_path('atlas.php'),
            ], 'atlas-config');
        }
    }

    /**
     * Register built-in provider factories.
     */
    protected function registerProviders(): void
    {
        /** @var ProviderRegistryContract $registry */
        $registry = $this->app->make(ProviderRegistryContract::class);

        $registry->register(Provider::OpenAI->value, function (Application $app, array $config) {
            return new OpenAiDriver(
                config: ProviderConfig::fromArray($config),
                http: $app->make(HttpClient::class),
                middlewareStack: $app->make(MiddlewareStack::class),
            );
        });

        $registry->register(Provider::xAI->value, function (Application $app, array $config) {
            return new XaiDriver(
                config: ProviderConfig::fromArray($config),
                http: $app->make(HttpClient::class),
                middlewareStack: $app->make(MiddlewareStack::class),
            );
        });

        $registry->register('cohere', function (Application $app, array $config) {
            return new CohereDriver(
                config: ProviderConfig::fromArray($config),
                http: $app->make(HttpClient::class),
                middlewareStack: $app->make(MiddlewareStack::class),
            );
        });

        $registry->register('jina', function (Application $app, array $config) {
            return new JinaDriver(
                config: ProviderConfig::fromArray($config),
                http: $app->make(HttpClient::class),
                middlewareStack: $app->make(MiddlewareStack::class),
            );
        });
    }
}
