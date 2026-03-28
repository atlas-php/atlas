<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Middleware\Contracts;

/**
 * Marker interface for middleware that wraps all provider HTTP calls.
 *
 * Implement this interface directly to run on every provider call regardless
 * of modality. For modality-specific middleware, implement a sub-interface
 * like ImageMiddleware or TextMiddleware instead.
 *
 * Your class must define: handle(ProviderContext $ctx, Closure $next): mixed
 */
interface ProviderMiddleware {}
