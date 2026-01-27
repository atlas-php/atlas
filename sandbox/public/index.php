<?php

declare(strict_types=1);

/**
 * Web entry point for the Atlas sandbox.
 *
 * This file serves as the front controller for web requests
 * to the sandbox chat interface.
 */

use Illuminate\Http\Request;

// Bootstrap the application
$app = require __DIR__.'/../bootstrap.php';

// Create and handle the request
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
);

$response->send();

$kernel->terminate($request, $response);
