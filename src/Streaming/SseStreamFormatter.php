<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Streaming;

use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\StreamEvent;
use Prism\Prism\Streaming\Events\StreamStartEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;

/**
 * Formats Prism StreamEvent objects as Server-Sent Events (SSE).
 *
 * Converts stream events to the standard SSE wire format for HTTP streaming.
 */
class SseStreamFormatter
{
    /**
     * Format a stream event as an SSE string.
     */
    public function format(StreamEvent $event): string
    {
        $data = $this->eventToData($event);
        $eventType = $this->eventType($event);

        $output = "event: {$eventType}\n";
        $output .= 'data: '.json_encode($data, JSON_THROW_ON_ERROR)."\n\n";

        return $output;
    }

    /**
     * Format a done signal.
     */
    public function done(): string
    {
        return "event: done\ndata: [DONE]\n\n";
    }

    /**
     * Get the SSE event type name.
     */
    private function eventType(StreamEvent $event): string
    {
        return match (true) {
            $event instanceof StreamStartEvent => 'stream-start',
            $event instanceof TextDeltaEvent => 'text-delta',
            $event instanceof StreamEndEvent => 'stream-end',
            default => 'unknown',
        };
    }

    /**
     * Convert a stream event to a data array.
     *
     * @return array<string, mixed>
     */
    private function eventToData(StreamEvent $event): array
    {
        return match (true) {
            $event instanceof StreamStartEvent => [
                'id' => $event->id,
                'model' => $event->model,
                'provider' => $event->provider,
            ],
            $event instanceof TextDeltaEvent => [
                'id' => $event->id,
                'delta' => $event->delta,
            ],
            $event instanceof StreamEndEvent => [
                'id' => $event->id,
                'finish_reason' => $event->finishReason->value,
                'usage' => [
                    'prompt_tokens' => $event->usage->promptTokens,
                    'completion_tokens' => $event->usage->completionTokens,
                ],
            ],
            default => [
                'id' => $event->id,
            ],
        };
    }
}
