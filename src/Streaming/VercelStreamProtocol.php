<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Streaming;

use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\StreamEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;

/**
 * Converts Prism StreamEvent objects to the Vercel AI SDK wire format.
 *
 * Implements the Vercel AI SDK Data Stream Protocol for compatibility
 * with @ai-sdk/react useChat() and useCompletion() hooks.
 *
 * @see https://sdk.vercel.ai/docs/ai-sdk-ui/stream-protocol
 */
class VercelStreamProtocol
{
    /**
     * Format a stream event in Vercel AI SDK wire format.
     *
     * Returns null for events that have no Vercel protocol equivalent.
     */
    public function format(StreamEvent $event): ?string
    {
        return match (true) {
            $event instanceof TextDeltaEvent => $this->formatTextDelta($event),
            $event instanceof StreamEndEvent => $this->formatFinish($event),
            default => null,
        };
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
     * Format a stream finish event.
     *
     * Vercel format: `d:{"finishReason":"stop","usage":{"promptTokens":10,"completionTokens":5}}\n`
     */
    private function formatFinish(StreamEndEvent $event): string
    {
        $data = [
            'finishReason' => $event->finishReason->value,
            'usage' => [
                'promptTokens' => $event->usage->promptTokens,
                'completionTokens' => $event->usage->completionTokens,
            ],
        ];

        return 'd:'.json_encode($data, JSON_THROW_ON_ERROR)."\n";
    }
}
