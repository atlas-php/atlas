<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Middleware;

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Messages\Message;
use Atlasphp\Atlas\Requests\TextRequest;

/**
 * Context for agent-layer middleware.
 *
 * Wraps the entire agent execution — from first message to final result.
 */
class AgentContext
{
    /**
     * @param  array<int, Message>  $messages
     * @param  array<int, mixed>  $tools
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public TextRequest $request,
        public readonly ?Agent $agent = null,
        public array $messages = [],
        public readonly array $tools = [],
        public array $meta = [],
    ) {}
}
