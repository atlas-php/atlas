<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\OpenAi;

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Exceptions\ProviderException;
use Atlasphp\Atlas\Providers\Contracts\ResponseParser as ResponseParserContract;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;

/**
 * Parses OpenAI Responses API output into Atlas response objects.
 *
 * Handles typed output items (message, function_call, reasoning),
 * status-based finish reasons, and named SSE streaming events.
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
        /** @var array<int, array<string, mixed>> $output */
        $output = $data['output'] ?? [];

        $text = '';
        $reasoning = null;
        $functionCalls = [];

        foreach ($output as $item) {
            $type = $item['type'] ?? null;

            if ($type === 'message') {
                $text .= $this->extractMessageText($item);
            }

            if ($type === 'reasoning') {
                $reasoning = $this->extractReasoningText($item);
            }

            if ($type === 'function_call') {
                $functionCalls[] = $item;
            }
        }

        $toolCalls = $functionCalls !== []
            ? $this->toolMapper->parseToolCalls($functionCalls)
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
            inputTokens: (int) ($usage['input_tokens'] ?? 0),
            outputTokens: (int) ($usage['output_tokens'] ?? 0),
            reasoningTokens: isset($usage['output_tokens_details']['reasoning_tokens'])
                ? (int) $usage['output_tokens_details']['reasoning_tokens']
                : null,
            cachedTokens: isset($usage['input_tokens_details']['cached_tokens'])
                ? (int) $usage['input_tokens_details']['cached_tokens']
                : null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function parseFinishReason(array $data): FinishReason
    {
        /** @var array<int, array<string, mixed>> $output */
        $output = $data['output'] ?? [];

        $hasFunctionCalls = false;

        foreach ($output as $item) {
            if (($item['type'] ?? null) === 'function_call') {
                $hasFunctionCalls = true;

                break;
            }
        }

        if ($hasFunctionCalls) {
            return FinishReason::ToolCalls;
        }

        $status = (string) ($data['status'] ?? 'completed');

        if ($status === 'incomplete') {
            $reason = (string) ($data['incomplete_details']['reason'] ?? '');

            return match ($reason) {
                'max_output_tokens' => FinishReason::Length,
                'content_filter' => FinishReason::ContentFilter,
                default => FinishReason::Stop,
            };
        }

        return FinishReason::Stop;
    }

    /**
     * Parse a streaming event into a StreamChunk.
     *
     * Receives an array with 'event' (the SSE event name) and 'data' (parsed JSON).
     *
     * @param  array<string, mixed>  $data
     */
    public function parseStreamChunk(array $data): StreamChunk
    {
        $event = (string) ($data['event'] ?? '');
        /** @var array<string, mixed> $payload */
        $payload = $data['data'] ?? [];

        if (str_contains($event, 'output_text.delta')) {
            return new StreamChunk(
                type: ChunkType::Text,
                text: (string) ($payload['delta'] ?? ''),
            );
        }

        if (str_contains($event, 'function_call_arguments.done')) {
            return new StreamChunk(
                type: ChunkType::ToolCall,
                toolCalls: $this->toolMapper->parseToolCalls([
                    [
                        'call_id' => $payload['call_id'] ?? '',
                        'name' => $payload['name'] ?? '',
                        'arguments' => $payload['arguments'] ?? '{}',
                    ],
                ]),
            );
        }

        if ($event === 'response.completed') {
            /** @var array<string, mixed> $response */
            $response = $payload['response'] ?? $payload;

            $usage = isset($response['usage']) ? $this->parseUsage($response) : null;
            $finishReason = $this->parseFinishReason($response);

            return new StreamChunk(
                type: ChunkType::Done,
                usage: $usage,
                finishReason: $finishReason,
            );
        }

        if ($event === 'response.failed') {
            $error = (string) ($payload['response']['error']['message'] ?? 'Response generation failed');

            throw new ProviderException('openai', '', 0, $error);
        }

        return new StreamChunk(type: ChunkType::Text, text: null);
    }

    /**
     * Extract text from a message output item.
     *
     * @param  array<string, mixed>  $item
     */
    private function extractMessageText(array $item): string
    {
        $text = '';

        /** @var array<int, array<string, mixed>> $content */
        $content = $item['content'] ?? [];

        foreach ($content as $part) {
            if (($part['type'] ?? null) === 'output_text') {
                $text .= (string) ($part['text'] ?? '');
            }
        }

        return $text;
    }

    /**
     * Extract reasoning text from a reasoning output item.
     *
     * @param  array<string, mixed>  $item
     */
    private function extractReasoningText(array $item): ?string
    {
        $text = '';

        // OpenAI uses summary for reasoning output
        /** @var array<int, array<string, mixed>> $summaries */
        $summaries = $item['summary'] ?? [];

        foreach ($summaries as $summary) {
            if (($summary['type'] ?? null) === 'summary_text') {
                $text .= (string) ($summary['text'] ?? '');

                if ($text !== '') {
                    $text .= "\n";
                }
            }
        }

        // xAI and other providers use content for reasoning output
        if ($text === '') {
            /** @var array<int, array<string, mixed>> $content */
            $content = $item['content'] ?? [];

            foreach ($content as $part) {
                $partText = (string) ($part['text'] ?? '');

                if ($partText !== '') {
                    $text .= $partText."\n";
                }
            }
        }

        $text = rtrim($text, "\n");

        return $text !== '' ? $text : null;
    }
}
