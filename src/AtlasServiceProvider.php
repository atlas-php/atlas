<?php

declare(strict_types=1);

namespace Atlasphp\Atlas;

use Atlasphp\Atlas\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderRegistry;
use Illuminate\Contracts\Events\Dispatcher;
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
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/atlas.php' => config_path('atlas.php'),
            ], 'atlas-config');
        }
    }
}
