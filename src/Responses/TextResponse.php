<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses;

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Executor\Step;
use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\ToolCall;

/**
 * Response from a text generation request.
 *
 * When tools are present, the steps array contains the full execution trace
 * from the agent loop — each step records the model's response, tool calls,
 * tool results, and token usage for that round trip.
 */
class TextResponse
{
    /**
     * @param  array<int, ToolCall>  $toolCalls
     * @param  array<int, Step>  $steps
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $text,
        public readonly Usage $usage,
        public readonly FinishReason $finishReason,
        public readonly array $toolCalls = [],
        public readonly ?string $reasoning = null,
        public readonly array $steps = [],
        public readonly array $meta = [],
    ) {}

    /**
     * Determine if the response includes tool calls.
     */
    public function hasToolCalls(): bool
    {
        return ! empty($this->toolCalls);
    }

    /**
     * Convert this response to an assistant message for conversation history.
     */
    public function toMessage(): AssistantMessage
    {
        return new AssistantMessage(
            content: $this->text,
            toolCalls: $this->toolCalls,
            reasoning: $this->reasoning,
        );
    }
}
