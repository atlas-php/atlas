<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\ChatCompletions;

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Providers\Contracts\ResponseParser as ResponseParserContract;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;

/**
 * Parses Chat Completions API responses into Atlas response objects.
 *
 * Reads from choices[0].message, maps prompt_tokens/completion_tokens,
 * and handles data-only SSE streaming.
 */
class ResponseParser implements ResponseParserContract
{
    public function __construct(
        protected readonly ToolMapper $toolMapper,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function parseText(array $data): TextResponse
    {
        /** @var array<string, mixed> $message */
        $message = $data['choices'][0]['message'] ?? [];

        $toolCalls = ! empty($message['tool_calls'])
            ? $this->toolMapper->parseToolCalls($message['tool_calls'])
            : [];

        return new TextResponse(
            text: (string) ($message['content'] ?? ''),
            usage: $this->parseUsage($data),
            finishReason: $this->parseFinishReason($data),
            toolCalls: $toolCalls,
            reasoning: isset($message['reasoning_content']) ? (string) $message['reasoning_content'] : null,
            meta: [
                'id' => $data['id'] ?? null,
                'model' => $data['model'] ?? null,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function parseUsage(array $data): Usage
    {
        /** @var array<string, mixed> $usage */
        $usage = $data['usage'] ?? [];

        return new Usage(
            inputTokens: (int) ($usage['prompt_tokens'] ?? 0),
            outputTokens: (int) ($usage['completion_tokens'] ?? 0),
            reasoningTokens: isset($usage['completion_tokens_details']['reasoning_tokens'])
                ? (int) $usage['completion_tokens_details']['reasoning_tokens']
                : null,
            cachedTokens: isset($usage['prompt_tokens_details']['cached_tokens'])
                ? (int) $usage['prompt_tokens_details']['cached_tokens']
                : null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function parseFinishReason(array $data): FinishReason
    {
        return match ($data['choices'][0]['finish_reason'] ?? 'stop') {
            'stop' => FinishReason::Stop,
            'tool_calls' => FinishReason::ToolCalls,
            'length' => FinishReason::Length,
            'content_filter' => FinishReason::ContentFilter,
            default => FinishReason::Stop,
        };
    }

    /**
     * Parse a data-only SSE stream chunk.
     *
     * @param  array<string, mixed>  $data
     */
    public function parseStreamChunk(array $data): StreamChunk
    {
        /** @var array<string, mixed> $delta */
        $delta = $data['choices'][0]['delta'] ?? [];
        $finishReason = $data['choices'][0]['finish_reason'] ?? null;

        if (isset($delta['content'])) {
            return new StreamChunk(type: ChunkType::Text, text: (string) $delta['content']);
        }

        if (isset($delta['tool_calls'])) {
            return new StreamChunk(
                type: ChunkType::ToolCall,
                toolCalls: $this->toolMapper->parseToolCalls($delta['tool_calls']),
            );
        }

        if ($finishReason !== null) {
            return new StreamChunk(
                type: ChunkType::Done,
                finishReason: $this->parseFinishReason($data),
                usage: isset($data['usage']) ? $this->parseUsage($data) : null,
            );
        }

        return new StreamChunk(type: ChunkType::Text, text: null);
    }
}
