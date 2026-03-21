<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Atlas sandbox environment.
 *
 * Registers sandbox-specific configuration, routes, and views
 * for testing Atlas v3 functionality against real AI providers.
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
    }

    /**
     * Register web routes.
     */
    protected function registerRoutes(): void
    {
        $routesPath = dirname(__DIR__, 2).'/routes/web.php';

        if (file_exists($routesPath)) {
            Route::middleware('web')->group($routesPath);
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
}
