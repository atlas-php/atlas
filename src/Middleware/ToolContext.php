<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Middleware;

use Atlasphp\Atlas\Messages\ToolCall;

/**
 * Context for tool-layer middleware.
 *
 * Wraps each individual tool execution.
 */
class ToolContext
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly ToolCall $toolCall,
        public array $meta = [],
        public readonly ?int $stepNumber = null,
        public readonly ?string $agentKey = null,
    ) {}
}
