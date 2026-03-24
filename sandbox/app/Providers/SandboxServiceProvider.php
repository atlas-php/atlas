<?php

declare(strict_types=1);

namespace App\Providers;

use App\Agents\AssistantAgent;
use App\Agents\MemoryTestAgent;
use App\Agents\VoiceAssistantAgent;
use App\Console\FreshCommand;
use App\Listeners\SummarizeVoiceCall;
use Atlasphp\Atlas\Agents\AgentRegistry;
use Atlasphp\Atlas\Embeddings\VectorQueryMacros;
use Atlasphp\Atlas\Events\VoiceCallCompleted;
use Atlasphp\Atlas\Persistence\Middleware\PersistConversation;
use Atlasphp\Atlas\Persistence\Middleware\TrackExecution;
use Atlasphp\Atlas\Persistence\Middleware\TrackProviderCall;
use Atlasphp\Atlas\Persistence\Middleware\TrackStep;
use Atlasphp\Atlas\Persistence\Middleware\TrackToolCall;
use Atlasphp\Atlas\Persistence\Middleware\WireMemory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Atlas sandbox environment.
 *
 * Registers sandbox-specific configuration, routes, views, migrations,
 * commands, and persistence middleware. Middleware is registered here
 * because Orchestra Testbench boots providers before sandbox config
 * is loaded, so AtlasServiceProvider's auto-registration misses the
 * persistence.enabled flag.
 */
class SandboxServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerViews();
        $this->loadMigrations();
        $this->registerCommands();
        $this->registerAgents();
        $this->registerPersistenceMiddleware();
        $this->registerListeners();
    }

    /**
     * Register event listeners.
     */
    protected function registerListeners(): void
    {
        Event::listen(
            VoiceCallCompleted::class,
            SummarizeVoiceCall::class,
        );
    }

    /**
     * Register agents with the registry.
     */
    protected function registerAgents(): void
    {
        /** @var AgentRegistry $registry */
        $registry = $this->app->make(AgentRegistry::class);
        $registry->register(AssistantAgent::class);
        $registry->register(VoiceAssistantAgent::class);
        $registry->register(MemoryTestAgent::class);
    }

    /**
     * Register persistence and memory middleware.
     *
     * AtlasServiceProvider auto-registers these when persistence.enabled
     * is true during boot — but in the sandbox, Orchestra Testbench boots
     * before our config override is applied. So we wire them explicitly.
     */
    protected function registerPersistenceMiddleware(): void
    {
        if (! config('atlas.persistence.enabled')) {
            return;
        }

        // Memory middleware (before PersistConversation for variable registration)
        $agentMiddleware = config('atlas.middleware.agent', []);

        if (! in_array(WireMemory::class, $agentMiddleware, true)) {
            array_unshift($agentMiddleware, WireMemory::class);
        }

        if (! in_array(PersistConversation::class, $agentMiddleware, true)) {
            $agentMiddleware[] = PersistConversation::class;
        }

        if (! in_array(TrackExecution::class, $agentMiddleware, true)) {
            $agentMiddleware[] = TrackExecution::class;
        }

        config(['atlas.middleware.agent' => $agentMiddleware]);

        $stepMiddleware = config('atlas.middleware.step', []);

        if (! in_array(TrackStep::class, $stepMiddleware, true)) {
            $stepMiddleware[] = TrackStep::class;
        }

        config(['atlas.middleware.step' => $stepMiddleware]);

        $toolMiddleware = config('atlas.middleware.tool', []);

        if (! in_array(TrackToolCall::class, $toolMiddleware, true)) {
            $toolMiddleware[] = TrackToolCall::class;
        }

        config(['atlas.middleware.tool' => $toolMiddleware]);

        $providerMiddleware = config('atlas.middleware.provider', []);

        if (! in_array(TrackProviderCall::class, $providerMiddleware, true)) {
            $providerMiddleware[] = TrackProviderCall::class;
        }

        config(['atlas.middleware.provider' => $providerMiddleware]);

        VectorQueryMacros::register();
    }

    /**
     * Register web and API routes.
     */
    protected function registerRoutes(): void
    {
        $routesPath = dirname(__DIR__, 2).'/routes/web.php';

        if (file_exists($routesPath)) {
            Route::middleware('web')->group($routesPath);
        }

        $apiPath = dirname(__DIR__, 2).'/routes/api.php';

        if (file_exists($apiPath)) {
            Route::prefix('api')->group($apiPath);
        }
    }

    /**
     * Register view paths.
     */
    protected function registerViews(): void
    {
        $viewsPath = dirname(__DIR__, 2).'/resources/views';

        if (is_dir($viewsPath)) {
            $this->loadViewsFrom($viewsPath, 'sandbox');
            $this->app['view']->addLocation($viewsPath);
        }
    }

    /**
     * Load Atlas package and sandbox-specific migrations.
     */
    protected function loadMigrations(): void
    {
        $packageMigrations = dirname(__DIR__, 3).'/database/migrations';

        if (is_dir($packageMigrations)) {
            $this->loadMigrationsFrom($packageMigrations);
        }

        $sandboxMigrations = dirname(__DIR__, 2).'/database/migrations';

        if (is_dir($sandboxMigrations)) {
            $this->loadMigrationsFrom($sandboxMigrations);
        }
    }

    /**
     * Register sandbox console commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                FreshCommand::class,
            ]);
        }
    }
}
