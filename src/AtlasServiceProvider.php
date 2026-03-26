<?php

declare(strict_types=1);

namespace Atlasphp\Atlas;

use Atlasphp\Atlas\Embeddings\EmbeddingResolver;
use Atlasphp\Atlas\Embeddings\VectorQueryMacros;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Http\RetryDecider;
use Atlasphp\Atlas\Middleware\MiddlewareStack;
use Atlasphp\Atlas\Persistence\Services\ExecutionService;
use Atlasphp\Atlas\Providers\Anthropic\AnthropicDriver;
use Atlasphp\Atlas\Providers\Cohere\CohereDriver;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\ElevenLabs\ElevenLabsDriver;
use Atlasphp\Atlas\Providers\Google\GoogleDriver;
use Atlasphp\Atlas\Providers\Jina\JinaDriver;
use Atlasphp\Atlas\Providers\OpenAi\OpenAiDriver;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\ProviderRegistry;
use Atlasphp\Atlas\Providers\Xai\XaiDriver;
use Atlasphp\Atlas\Support\VariableRegistry;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
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

        // Register persistence middleware into config BEFORE AtlasConfig
        // singleton is created, so the snapshot includes them.
        $this->registerPersistenceMiddleware();

        $this->app->singleton(AtlasConfig::class, fn () => AtlasConfig::fromConfig());

        $this->app->singleton(ProviderRegistryContract::class, function ($app) {
            return new ProviderRegistry($app, $app->make(AtlasConfig::class));
        });

        $this->app->singleton(AgentRegistry::class, function ($app) {
            return new AgentRegistry($app);
        });

        $this->app->singleton(AtlasManager::class, function ($app) {
            return new AtlasManager(
                $app->make(ProviderRegistryContract::class),
                $app,
                $app->make(AtlasConfig::class),
            );
        });

        $this->app->singleton(RetryDecider::class);

        $this->app->singleton(HttpClient::class, function ($app) {
            return new HttpClient(
                $app->make(Dispatcher::class),
                $app->make(RetryDecider::class),
            );
        });

        $this->app->singleton(MiddlewareStack::class, function ($app) {
            return new MiddlewareStack($app);
        });

        $this->app->scoped(ExecutionService::class);

        $this->app->singleton(VariableRegistry::class, function ($app) {
            return new VariableRegistry($app->make(AtlasConfig::class));
        });

        $this->app->singleton(AtlasCache::class, function ($app) {
            return new AtlasCache($app->make(AtlasConfig::class));
        });
        $this->app->singleton(EmbeddingResolver::class, function ($app) {
            return new EmbeddingResolver(
                $app->make(AtlasCache::class),
                $app->make(AtlasConfig::class),
            );
        });
    }

    public function boot(): void
    {
        $this->registerProviders();
        $this->discoverAgents();

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\MakeAgentCommand::class,
                Console\MakeToolCommand::class,
                Console\CleanStaleVoiceSessionsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/atlas.php' => config_path('atlas.php'),
            ], 'atlas-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'atlas-migrations');
        }

        $this->registerBuiltInVariables();
        $this->registerVoiceRoutes();
        $this->registerVectorMacros();
    }

    /**
     * Auto-register persistence middleware when enabled.
     */
    /**
     * Append persistence middleware to config before AtlasConfig is created.
     *
     * Called in register() BEFORE the AtlasConfig singleton binding so the
     * snapshot includes these middleware classes. Config remains the source
     * of truth — consumers can see all middleware via config('atlas.middleware').
     */
    protected function registerPersistenceMiddleware(): void
    {
        if (! config('atlas.persistence.enabled')) {
            return;
        }

        $agent = config('atlas.middleware.agent', []);
        $agent[] = Persistence\Middleware\PersistConversation::class;
        $agent[] = Persistence\Middleware\TrackExecution::class;
        config(['atlas.middleware.agent' => $agent]);

        $step = config('atlas.middleware.step', []);
        $step[] = Persistence\Middleware\TrackStep::class;
        config(['atlas.middleware.step' => $step]);

        $tool = config('atlas.middleware.tool', []);
        $tool[] = Persistence\Middleware\TrackToolCall::class;
        config(['atlas.middleware.tool' => $tool]);

        $provider = config('atlas.middleware.provider', []);
        $provider[] = Persistence\Middleware\TrackProviderCall::class;
        config(['atlas.middleware.provider' => $provider]);
    }

    /**
     * Register package HTTP routes for voice sessions and transcript persistence.
     */
    protected function registerVoiceRoutes(): void
    {
        $this->app->booted(function (): void {
            $config = app(AtlasConfig::class);
            $prefix = $config->voiceTranscripts['route_prefix'] ?? 'atlas';
            $middleware = $config->voiceTranscripts['middleware'] ?? [];

            Route::prefix($prefix)
                ->middleware($middleware)
                ->group(function (): void {
                    Route::post(
                        '/voice/{sessionId}/tool',
                        Voice\Http\VoiceToolController::class,
                    );
                    Route::post(
                        '/voice/{sessionId}/transcript',
                        Persistence\Http\StoreVoiceTranscriptController::class,
                    );
                    Route::post(
                        '/voice/{sessionId}/close',
                        Voice\Http\CloseVoiceSessionController::class,
                    );
                });
        });
    }

    /**
     * Register pgvector query macros when persistence is enabled.
     */
    protected function registerVectorMacros(): void
    {
        if (! app(AtlasConfig::class)->persistenceEnabled) {
            return;
        }

        VectorQueryMacros::register();
    }

    /**
     * Auto-discover agent classes from the configured directory.
     */
    protected function discoverAgents(): void
    {
        /** @var array<string, string|null> $config */
        $config = $this->app['config']->get('atlas.agents', []);

        $path = $config['path'] ?? null;
        $namespace = $config['namespace'] ?? null;

        if ($path !== null && $namespace !== null) {
            /** @var AgentRegistry $registry */
            $registry = $this->app->make(AgentRegistry::class);
            $registry->discover($path, $namespace);
        }
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
                cache: $app->make(AtlasCache::class),
            );
        });

        $registry->register(Provider::xAI->value, function (Application $app, array $config) {
            return new XaiDriver(
                config: ProviderConfig::fromArray($config),
                http: $app->make(HttpClient::class),
                middlewareStack: $app->make(MiddlewareStack::class),
                cache: $app->make(AtlasCache::class),
            );
        });

        $registry->register(Provider::Anthropic->value, function (Application $app, array $config) {
            return new AnthropicDriver(
                config: ProviderConfig::fromArray($config),
                http: $app->make(HttpClient::class),
                middlewareStack: $app->make(MiddlewareStack::class),
                cache: $app->make(AtlasCache::class),
            );
        });

        $registry->register(Provider::Google->value, function (Application $app, array $config) {
            return new GoogleDriver(
                config: ProviderConfig::fromArray($config),
                http: $app->make(HttpClient::class),
                middlewareStack: $app->make(MiddlewareStack::class),
                cache: $app->make(AtlasCache::class),
            );
        });

        $registry->register(Provider::ElevenLabs->value, function (Application $app, array $config) {
            return new ElevenLabsDriver(
                config: ProviderConfig::fromArray($config),
                http: $app->make(HttpClient::class),
                middlewareStack: $app->make(MiddlewareStack::class),
                cache: $app->make(AtlasCache::class),
            );
        });

        $registry->register('cohere', function (Application $app, array $config) {
            return new CohereDriver(
                config: ProviderConfig::fromArray($config),
                http: $app->make(HttpClient::class),
                middlewareStack: $app->make(MiddlewareStack::class),
                cache: $app->make(AtlasCache::class),
            );
        });

        $registry->register('jina', function (Application $app, array $config) {
            return new JinaDriver(
                config: ProviderConfig::fromArray($config),
                http: $app->make(HttpClient::class),
                middlewareStack: $app->make(MiddlewareStack::class),
                cache: $app->make(AtlasCache::class),
            );
        });
    }
}
