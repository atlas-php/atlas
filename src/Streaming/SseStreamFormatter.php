<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Streaming;

use Prism\Prism\Streaming\Events\StreamEvent;

/**
 * Formats Prism StreamEvent objects as Server-Sent Events (SSE).
 *
 * Converts stream events to the standard SSE wire format for HTTP streaming.
 * Uses Prism's eventKey() and toArray() for automatic support of all event types.
 */
class SseStreamFormatter
{
    /**
     * Format a stream event as an SSE string.
     */
    public function format(StreamEvent $event): string
    {
        $eventType = $event->eventKey();
        $data = $event->toArray();

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
}
