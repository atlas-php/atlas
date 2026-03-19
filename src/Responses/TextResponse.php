<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses;

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\ToolCall;

/**
 * Response from a text generation request.
 */
class TextResponse
{
    /**
     * @param  array<int, ToolCall>  $toolCalls
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $text,
        public readonly Usage $usage,
        public readonly FinishReason $finishReason,
        public readonly array $toolCalls = [],
        public readonly ?string $reasoning = null,
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
