<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Middleware\Contracts;

/**
 * Marker interface for middleware that wraps each step in the tool call loop.
 *
 * Implement this interface and add your class to the `atlas.middleware` config
 * array. Atlas will route it to the step execution pipeline automatically.
 *
 * Your class must define: handle(StepContext $ctx, Closure $next): mixed
 */
interface StepMiddleware {}
