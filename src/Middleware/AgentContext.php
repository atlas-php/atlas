<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Middleware;

use Atlasphp\Atlas\Requests\TextRequest;

/**
 * Context for agent-layer middleware.
 *
 * Wraps the entire agent execution — from first message to final result.
 * Scaffolded for Phase 7 when the Agent class is implemented.
 */
class AgentContext
{
    /**
     * @param  array<int, mixed>  $tools
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public TextRequest $request,
        public readonly array $tools = [],
        public array $meta = [],
    ) {}
}
