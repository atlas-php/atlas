<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Google;

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Providers\Contracts\ResponseParser as ResponseParserContract;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;

/**
 * Parses Gemini's candidates response format into Atlas response objects.
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
        $candidate = $data['candidates'][0] ?? [];
        $parts = $candidate['content']['parts'] ?? [];

        $reasoning = null;
        $functionCallParts = [];
        $hasThinking = false;

        // First pass: detect thinking parts and collect function calls
        foreach ($parts as $part) {
            if (isset($part['thought']) && $part['thought'] === true && isset($part['text'])) {
                $hasThinking = true;
                $reasoning = ($reasoning ?? '').$part['text'];
            }

            if (isset($part['functionCall'])) {
                $functionCallParts[] = $part;
            }
        }

        // Second pass: extract text (exclude thinking parts if present)
        $text = '';
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                if ($hasThinking && isset($part['thought']) && $part['thought'] === true) {
                    continue;
                }
                $text .= $part['text'];
            }
        }

        $toolCalls = $functionCallParts !== []
            ? $this->toolMapper->parseToolCalls($functionCallParts)
            : [];

        return new TextResponse(
            text: $text,
            usage: $this->parseUsage($data),
            finishReason: $this->parseFinishReason($data),
            toolCalls: $toolCalls,
            reasoning: $reasoning,
            meta: [
                'model' => $data['modelVersion'] ?? null,
                'groundingMeta' => $candidate['groundingMetadata'] ?? null,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function parseUsage(array $data): Usage
    {
        $usage = $data['usageMetadata'] ?? [];

        return new Usage(
            inputTokens: (int) ($usage['promptTokenCount'] ?? 0),
            outputTokens: (int) ($usage['candidatesTokenCount'] ?? 0),
            reasoningTokens: isset($usage['thoughtsTokenCount']) ? (int) $usage['thoughtsTokenCount'] : null,
            cachedTokens: isset($usage['cachedContentTokenCount']) ? (int) $usage['cachedContentTokenCount'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function parseFinishReason(array $data): FinishReason
    {
        $reason = $data['candidates'][0]['finishReason'] ?? 'STOP';
        $parts = $data['candidates'][0]['content']['parts'] ?? [];

        $hasFunctionCalls = array_filter($parts, fn (array $p): bool => isset($p['functionCall'])) !== [];

        if ($hasFunctionCalls) {
            return FinishReason::ToolCalls;
        }

        return match ($reason) {
            'STOP' => FinishReason::Stop,
            'MAX_TOKENS' => FinishReason::Length,
            'SAFETY', 'RECITATION', 'BLOCKLIST' => FinishReason::ContentFilter,
            default => FinishReason::Stop,
        };
    }

    /**
     * Parse a streaming SSE data payload into a StreamChunk.
     *
     * @param  array<string, mixed>  $data
     */
    public function parseStreamChunk(array $data): StreamChunk
    {
        $candidate = $data['candidates'][0] ?? [];
        $parts = $candidate['content']['parts'] ?? [];
        $finishReason = $candidate['finishReason'] ?? null;

        // Gemini's final chunk often carries both text content AND finishReason + usageMetadata.
        // Extract usage from the terminal chunk regardless of content.
        $usage = $finishReason !== null ? $this->parseUsage($data) : null;

        foreach ($parts as $part) {
            if (isset($part['functionCall'])) {
                return new StreamChunk(
                    type: ChunkType::ToolCall,
                    toolCalls: $this->toolMapper->parseToolCalls([$part]),
                    usage: $usage,
                );
            }

            if (isset($part['thought']) && $part['thought'] === true && isset($part['text'])) {
                return new StreamChunk(
                    type: ChunkType::Thinking,
                    reasoning: $part['text'],
                    usage: $usage,
                );
            }

            if (isset($part['text'])) {
                return new StreamChunk(
                    type: ChunkType::Text,
                    text: $part['text'],
                    usage: $usage,
                );
            }
        }

        if ($finishReason !== null) {
            return new StreamChunk(
                type: ChunkType::Done,
                usage: $usage,
            );
        }

        return new StreamChunk(type: ChunkType::Text, text: null);
    }
}
