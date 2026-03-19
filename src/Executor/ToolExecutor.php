<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Executor;

use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Tools\ToolSerializer;

/**
 * Resolves and executes a single tool call.
 *
 * Looks up the tool by name in the registry, invokes handle() with the
 * call's arguments and meta, and serializes the result. Does not catch
 * exceptions — that responsibility belongs to the AgentExecutor.
 */
class ToolExecutor
{
    public function __construct(
        protected readonly ToolRegistry $registry,
    ) {}

    /**
     * Execute a tool call and return the serialized result.
     *
     * @param  array<string, mixed>  $meta
     */
    public function execute(ToolCall $toolCall, array $meta): ToolResult
    {
        $tool = $this->registry->resolve($toolCall->name);

        $result = $tool->handle($toolCall->arguments, $meta);

        return new ToolResult(
            toolCall: $toolCall,
            content: ToolSerializer::serialize($result),
        );
    }
}
