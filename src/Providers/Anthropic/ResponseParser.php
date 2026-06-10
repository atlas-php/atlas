<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Anthropic;

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Exceptions\ProviderException;
use Atlasphp\Atlas\Providers\Contracts\ResponseParserContract;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;

/**
 * Parses Anthropic's Messages API response format into Atlas response objects.
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
        $content = $data['content'] ?? [];

        $text = '';
        $reasoning = null;
        $toolUseBlocks = [];
        $providerToolCalls = [];
        $annotations = [];

        foreach ($content as $block) {
            $blockType = $block['type'] ?? '';

            if ($blockType === 'text') {
                $text .= $block['text'] ?? '';

                // Web-search/fetch citations ride on text blocks.
                foreach ($block['citations'] ?? [] as $citation) {
                    $annotations[] = $citation;
                }
            }

            if ($blockType === 'thinking') {
                $reasoning = ($reasoning ?? '').($block['thinking'] ?? '');
            }

            // Client tools the executor must run.
            if ($blockType === 'tool_use') {
                $toolUseBlocks[] = $block;
            }

            // Provider-executed (server-side) tools and their result blocks are
            // observability only — they must NOT enter the client tool loop, or
            // the executor would try to resolve a PHP handler for `web_search`.
            if ($blockType === 'server_tool_use'
                || $blockType === 'web_search_tool_result'
                || $blockType === 'web_fetch_tool_result') {
                $providerToolCalls[] = $block;
            }
        }

        $toolCalls = $toolUseBlocks !== []
            ? $this->toolMapper->parseToolCalls($toolUseBlocks)
            : [];

        return new TextResponse(
            text: $text,
            usage: $this->parseUsage($data),
            finishReason: $this->parseFinishReason($data),
            toolCalls: $toolCalls,
            reasoning: $reasoning,
            meta: [
                'id' => $data['id'] ?? null,
                'model' => $data['model'] ?? null,
            ],
            providerToolCalls: $providerToolCalls,
            annotations: $annotations,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function parseUsage(array $data): Usage
    {
        $usage = $data['usage'] ?? [];

        return new Usage(
            inputTokens: (int) ($usage['input_tokens'] ?? 0),
            outputTokens: (int) ($usage['output_tokens'] ?? 0),
            cachedTokens: isset($usage['cache_read_input_tokens']) ? (int) $usage['cache_read_input_tokens'] : null,
            cacheWriteTokens: isset($usage['cache_creation_input_tokens']) ? (int) $usage['cache_creation_input_tokens'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function parseFinishReason(array $data): FinishReason
    {
        return $this->mapStopReason($data['stop_reason'] ?? 'end_turn');
    }

    /**
     * Parse a streaming SSE event into a StreamChunk.
     *
     * @param  array<string, mixed>  $data
     */
    public function parseStreamChunk(array $data, string $model = ''): StreamChunk
    {
        $event = $data['event'] ?? '';
        $payload = $data['data'] ?? [];

        if ($event === 'error') {
            $error = $payload['error'] ?? $payload;

            throw ProviderException::fromStreamError('anthropic', $model, is_array($error) ? $error : ['message' => (string) $error]);
        }

        if ($event === 'content_block_delta') {
            $delta = $payload['delta'] ?? [];
            $type = $delta['type'] ?? '';

            if ($type === 'text_delta') {
                return new StreamChunk(
                    type: ChunkType::Text,
                    text: $delta['text'] ?? '',
                );
            }

            if ($type === 'thinking_delta') {
                return new StreamChunk(
                    type: ChunkType::Thinking,
                    reasoning: $delta['thinking'] ?? '',
                );
            }
        }

        // Note: In production streaming, message_delta is handled directly by
        // Text::parseSSE() which combines stashed input tokens from message_start.
        // This branch is retained for direct parseStreamChunk() callers and tests.
        if ($event === 'message_delta') {
            $delta = $payload['delta'] ?? [];
            $usage = $payload['usage'] ?? [];

            return new StreamChunk(
                type: ChunkType::Done,
                usage: $usage !== [] ? new Usage(
                    inputTokens: (int) ($usage['input_tokens'] ?? 0),
                    outputTokens: (int) ($usage['output_tokens'] ?? 0),
                ) : null,
                finishReason: isset($delta['stop_reason'])
                    ? $this->mapStopReason($delta['stop_reason'])
                    : null,
            );
        }

        return new StreamChunk(type: ChunkType::Text, text: null);
    }

    private function mapStopReason(string $reason): FinishReason
    {
        return match ($reason) {
            'end_turn', 'stop_sequence' => FinishReason::Stop,
            'max_tokens' => FinishReason::Length,
            'tool_use', 'pause_turn' => FinishReason::ToolCalls,
            default => FinishReason::Stop,
        };
    }
}
