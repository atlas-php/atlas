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
use Orchestra\Testbench\Foundation\Application;

// Load the appropriate autoloader
// Prefer sandbox's own vendor if it exists, otherwise fall back to parent
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

// Define the sandbox base path
$sandboxPath = __DIR__;

// Create the application using Orchestra Testbench
/** @var \Illuminate\Foundation\Application $app */
$app = Application::create($sandboxPath);

// Set storage path
$app->useStoragePath($sandboxPath.'/storage');

// Register the service providers
$app->register(AtlasServiceProvider::class);
$app->register(SandboxServiceProvider::class);

// Boot the application
$app->boot();

// Load Atlas configuration from sandbox config directory
$app['config']->set('atlas', require $sandboxPath.'/config/atlas.php');
$app['config']->set('prism', require $sandboxPath.'/config/prism.php');

return $app;
