<?php

declare(strict_types=1);

namespace Atlasphp\Atlas;

use Atlasphp\Atlas\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Middleware\MiddlewareStack;
use Atlasphp\Atlas\Persistence\Services\ExecutionService;
use Atlasphp\Atlas\Providers\Anthropic\AnthropicDriver;
use Atlasphp\Atlas\Providers\Cohere\CohereDriver;
use Atlasphp\Atlas\Providers\Google\GoogleDriver;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\Jina\JinaDriver;
use Atlasphp\Atlas\Providers\OpenAi\OpenAiDriver;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\ProviderRegistry;
use Atlasphp\Atlas\Providers\Xai\XaiDriver;
use Atlasphp\Atlas\Support\VariableRegistry;
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

        $this->app->scoped(ExecutionService::class);

        $this->app->singleton(VariableRegistry::class);
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

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'atlas-migrations');
        }

        $this->registerBuiltInVariables();
        $this->registerPersistenceMiddleware();
    }

    /**
     * Auto-register persistence middleware when enabled.
     */
    protected function registerPersistenceMiddleware(): void
    {
        if (! config('atlas.persistence.enabled')) {
            return;
        }

        $this->app->booted(function (): void {
            $agentMiddleware = config('atlas.middleware.agent', []);
            $agentMiddleware[] = Persistence\Middleware\PersistConversation::class;
            $agentMiddleware[] = Persistence\Middleware\TrackExecution::class;
            config(['atlas.middleware.agent' => $agentMiddleware]);

            $stepMiddleware = config('atlas.middleware.step', []);
            $stepMiddleware[] = Persistence\Middleware\TrackStep::class;
            config(['atlas.middleware.step' => $stepMiddleware]);

            $toolMiddleware = config('atlas.middleware.tool', []);
            $toolMiddleware[] = Persistence\Middleware\TrackToolCall::class;
            config(['atlas.middleware.tool' => $toolMiddleware]);

            $providerMiddleware = config('atlas.middleware.provider', []);
            $providerMiddleware[] = Persistence\Middleware\TrackProviderCall::class;
            config(['atlas.middleware.provider' => $providerMiddleware]);
        });
    }

    /**
     * Register built-in variables available across all modalities.
     */
    protected function registerBuiltInVariables(): void
    {
        /** @var VariableRegistry $registry */
        $registry = $this->app->make(VariableRegistry::class);

        $registry->register('DATE', fn () => now()->toDateString());
        $registry->register('DATETIME', fn () => now()->toDateTimeString());
        $registry->register('TIME', fn () => now()->format('H:i:s'));
        $registry->register('TIMEZONE', fn () => config('app.timezone', 'UTC'));
        $registry->register('APP_NAME', fn () => config('app.name', 'Laravel'));
        $registry->register('APP_ENV', fn () => config('app.env', 'production'));
        $registry->register('APP_URL', fn () => config('app.url', 'http://localhost'));
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

        $registry->register(Provider::Anthropic->value, function (Application $app, array $config) {
            return new AnthropicDriver(
                config: ProviderConfig::fromArray($config),
                http: $app->make(HttpClient::class),
                middlewareStack: $app->make(MiddlewareStack::class),
            );
        });

        $registry->register(Provider::Google->value, function (Application $app, array $config) {
            return new GoogleDriver(
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
