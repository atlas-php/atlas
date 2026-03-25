<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses;

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Messages\ToolCall;

/**
 * A single chunk from a streaming response.
 */
class StreamChunk
{
    /**
     * @param  array<int, ToolCall>  $toolCalls
     */
    public function __construct(
        public readonly ChunkType $type,
        public readonly ?string $text = null,
        public readonly ?string $reasoning = null,
        public readonly array $toolCalls = [],
        public readonly ?Usage $usage = null,
        public readonly ?FinishReason $finishReason = null,
        public readonly ?int $stepNumber = null,
        public readonly ?string $toolName = null,
        public readonly ?string $toolCallId = null,
        public readonly ?string $toolContent = null,
        public readonly ?bool $toolError = null,
    ) {}
}
