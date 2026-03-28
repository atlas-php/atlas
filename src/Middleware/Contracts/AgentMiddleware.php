<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Middleware\Contracts;

/**
 * Marker interface for middleware that wraps entire agent executions.
 *
 * Implement this interface and add your class to the `atlas.middleware` config
 * array. Atlas will route it to the agent execution pipeline automatically.
 *
 * Your class must define: handle(AgentContext $ctx, Closure $next): mixed
 */
interface AgentMiddleware {}
