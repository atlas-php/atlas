<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Streaming;

use Prism\Prism\Streaming\Events\ErrorEvent;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\StreamEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Streaming\Events\ThinkingCompleteEvent;
use Prism\Prism\Streaming\Events\ThinkingEvent;
use Prism\Prism\Streaming\Events\ThinkingStartEvent;
use Prism\Prism\Streaming\Events\ToolCallEvent;
use Prism\Prism\Streaming\Events\ToolResultEvent;

/**
 * Converts Prism StreamEvent objects to the Vercel AI SDK wire format.
 *
 * Implements the Vercel AI SDK Data Stream Protocol for compatibility
 * with @ai-sdk/react useChat() and useCompletion() hooks.
 *
 * Supports thinking/reasoning events, tool calls, tool results, and errors
 * in addition to text deltas and stream end events.
 *
 * @see https://sdk.vercel.ai/docs/ai-sdk-ui/stream-protocol
 */
class VercelStreamProtocol
{
    private bool $hasToolCall = false;

    /**
     * Format a stream event in Vercel AI SDK wire format.
     *
     * Returns null for events that have no Vercel protocol equivalent.
     */
    public function format(StreamEvent $event): ?string
    {
        return match (true) {
            $event instanceof TextDeltaEvent => $this->formatTextDelta($event),
            $event instanceof ThinkingStartEvent => $this->formatReasoningStart($event),
            $event instanceof ThinkingEvent => $this->formatReasoningDelta($event),
            $event instanceof ThinkingCompleteEvent => $this->formatReasoningComplete(),
            $event instanceof ToolCallEvent => $this->formatToolCall($event),
            $event instanceof ToolResultEvent => $this->formatToolResult($event),
            $event instanceof ErrorEvent => $this->formatError($event),
            $event instanceof StreamEndEvent => $this->formatFinish($event),
            default => null,
        };
    }

    /**
     * Get the Vercel protocol headers.
     *
     * @return array<string, string>
     */
    public static function headers(): array
    {
        return [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'x-vercel-ai-ui-message-stream' => 'v1',
        ];
    }

    /**
     * Format a text delta event.
     *
     * Vercel format: `0:"text content"\n`
     */
    private function formatTextDelta(TextDeltaEvent $event): string
    {
        return '0:'.json_encode($event->delta, JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * Format a reasoning start event.
     *
     * Vercel format: `g:{"type":"reasoning-start","reasoningId":"..."}\n`
     */
    private function formatReasoningStart(ThinkingStartEvent $event): string
    {
        $data = [
            'type' => 'reasoning-start',
            'reasoningId' => $event->reasoningId,
        ];

        return 'g:'.json_encode($data, JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * Format a reasoning delta event.
     *
     * Vercel format: `g:{"type":"reasoning-delta","delta":"..."}\n`
     */
    private function formatReasoningDelta(ThinkingEvent $event): string
    {
        $data = [
            'type' => 'reasoning-delta',
            'delta' => $event->delta,
        ];

        return 'g:'.json_encode($data, JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * Format a reasoning complete event.
     *
     * Vercel format: `g:{"type":"reasoning-complete"}\n`
     */
    private function formatReasoningComplete(): string
    {
        $data = [
            'type' => 'reasoning-complete',
        ];

        return 'g:'.json_encode($data, JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * Format a tool call event.
     *
     * Vercel format: `9:{"toolCallId":"...","toolName":"...","args":{...}}\n`
     */
    private function formatToolCall(ToolCallEvent $event): string
    {
        $this->hasToolCall = true;

        $data = [
            'toolCallId' => $event->toolCall->id,
            'toolName' => $event->toolCall->name,
            'args' => $event->toolCall->arguments(),
        ];

        return '9:'.json_encode($data, JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * Format a tool result event.
     *
     * Returns null if no prior tool call was seen (orphan filtering).
     *
     * Vercel format: `a:{"toolCallId":"...","result":"..."}\n`
     */
    private function formatToolResult(ToolResultEvent $event): ?string
    {
        if (! $this->hasToolCall) {
            return null;
        }

        $data = [
            'toolCallId' => $event->toolResult->toolCallId,
            'result' => $event->toolResult->result,
        ];

        return 'a:'.json_encode($data, JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * Format an error event.
     *
     * Vercel format: `3:"error message"\n`
     */
    private function formatError(ErrorEvent $event): string
    {
        return '3:'.json_encode($event->message, JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * Format a stream finish event.
     *
     * Vercel format: `d:{"finishReason":"stop","usage":{"promptTokens":10,"completionTokens":5}}\n`
     */
    private function formatFinish(StreamEndEvent $event): string
    {
        $data = [
            'finishReason' => $event->finishReason->value,
            'usage' => [
                'promptTokens' => $event->usage !== null ? $event->usage->promptTokens : 0,
                'completionTokens' => $event->usage !== null ? $event->usage->completionTokens : 0,
            ],
        ];

        return 'd:'.json_encode($data, JSON_THROW_ON_ERROR)."\n";
    }
}
