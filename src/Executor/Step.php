<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Executor;

use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Responses\Usage;

/**
 * Represents one round trip in the executor loop.
 *
 * A step captures the model's response text, any tool calls it requested,
 * the results of executing those tools, and token usage for the round trip.
 */
class Step
{
    /**
     * @param  array<int, ToolCall>  $toolCalls
     * @param  array<int, ToolResult>  $toolResults
     */
    public function __construct(
        public readonly ?string $text,
        public readonly array $toolCalls,
        public readonly array $toolResults,
        public readonly Usage $usage,
    ) {}

    /**
     * Determine if this step includes tool calls.
     */
    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }
}
