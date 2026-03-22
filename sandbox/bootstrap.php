<?php

declare(strict_types=1);

/**
 * Bootstrap the sandbox environment.
 *
 * Creates a minimal Laravel application using Orchestra Testbench
 * with the Atlas package and sandbox service provider registered.
 */

use App\Providers\SandboxServiceProvider;
use Atlasphp\Atlas\AtlasServiceProvider;
use Dotenv\Dotenv;
use Laravel\Reverb\ApplicationManagerServiceProvider;
use Laravel\Reverb\ReverbServiceProvider;
use Orchestra\Testbench\Foundation\Application;

// Load the appropriate autoloader
$sandboxVendor = __DIR__.'/vendor/autoload.php';
$parentVendor = __DIR__.'/../vendor/autoload.php';

if (file_exists($sandboxVendor)) {
    require $sandboxVendor;
} elseif (file_exists($parentVendor)) {
    require $parentVendor;
} else {
    throw new RuntimeException(
        'Autoloader not found. Run "composer install" in the sandbox directory.'
    );
}

// Load environment variables from sandbox directory
if (file_exists(__DIR__.'/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

$sandboxPath = __DIR__;

/** @var Illuminate\Foundation\Application $app */
$app = Application::create($sandboxPath);

$app->useStoragePath($sandboxPath.'/storage');

// Load sandbox config (overrides package defaults set by mergeConfigFrom)
$app['config']->set('app', require $sandboxPath.'/config/app.php');
$app['config']->set('database', require $sandboxPath.'/config/database.php');
$app['config']->set('session', require $sandboxPath.'/config/session.php');
$app['config']->set('queue', require $sandboxPath.'/config/queue.php');
$app['config']->set('broadcasting', require $sandboxPath.'/config/broadcasting.php');
$app['config']->set('reverb', require $sandboxPath.'/config/reverb.php');
$app['config']->set('cache', require $sandboxPath.'/config/cache.php');
$app['config']->set('filesystems', require $sandboxPath.'/config/filesystems.php');
$app['config']->set('atlas', require $sandboxPath.'/config/atlas.php');

// Register providers — AtlasServiceProvider's mergeConfigFrom won't overwrite
// our values. But registerPersistenceMiddleware's booted() callback already
// missed the window (Application::create boots immediately), so
// SandboxServiceProvider handles middleware registration.
$app->register(AtlasServiceProvider::class);
$app->register(ApplicationManagerServiceProvider::class);
$app->register(ReverbServiceProvider::class);
$app->register(SandboxServiceProvider::class);

return $app;
