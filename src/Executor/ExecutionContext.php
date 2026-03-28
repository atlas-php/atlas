<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Executor;

use Illuminate\Broadcasting\Channel;

/**
 * Bundles identity and tracing fields for an executor run.
 *
 * Passed through the AgentExecutor to avoid spreading five nullable
 * parameters across every method signature and event dispatch.
 */
final class ExecutionContext
{
    public function __construct(
        public readonly ?string $agentKey = null,
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly ?string $traceId = null,
        public readonly ?Channel $broadcastChannel = null,
    ) {}
}
