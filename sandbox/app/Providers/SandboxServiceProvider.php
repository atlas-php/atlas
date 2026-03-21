<?php

declare(strict_types=1);

namespace App\Providers;

use App\Agents\AssistantAgent;
use App\Console\FreshCommand;
use Atlasphp\Atlas\Agents\AgentRegistry;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Atlas sandbox environment.
 *
 * Registers sandbox-specific configuration, routes, views, migrations,
 * and commands for testing Atlas v3 functionality against real AI providers.
 */
class SandboxServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->booted(function () {
            /** @var AgentRegistry $registry */
            $registry = $this->app->make(AgentRegistry::class);
            $registry->register(AssistantAgent::class);
        });
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
        // Atlas package migrations (conversations, messages, executions, etc.)
        $packageMigrations = dirname(__DIR__, 3).'/database/migrations';

        if (is_dir($packageMigrations)) {
            $this->loadMigrationsFrom($packageMigrations);
        }

        // Sandbox-specific migrations (users, jobs)
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
