<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Streaming;

use Prism\Prism\Streaming\Events\StreamEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Streaming\Events\ThinkingEvent;

/**
 * Shared helper for extracting data from Prism stream events.
 */
final class StreamEventHelper
{
    /**
     * Extract text delta from events that carry one.
     */
    public static function extractDelta(StreamEvent $event): ?string
    {
        return match (true) {
            $event instanceof TextDeltaEvent => $event->delta,
            $event instanceof ThinkingEvent => $event->delta,
            default => null,
        };
    }
}
