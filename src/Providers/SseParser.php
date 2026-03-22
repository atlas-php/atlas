<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers;

use Generator;
use Psr\Http\Message\StreamInterface;

/**
 * Shared SSE (Server-Sent Events) parser for provider stream responses.
 *
 * Reads a raw HTTP response body and yields parsed event/data tuples.
 * Handles named events, multi-line data fields, and the [DONE] sentinel.
 * Used by OpenAI and Anthropic stream handlers.
 */
class SseParser
{
    /**
     * Parse a named-event SSE stream (event: + data: lines, separated by \n\n).
     *
     * @return Generator<int, array{event: string, data: array<string, mixed>}>
     */
    public static function parse(mixed $rawResponse): Generator
    {
        /** @var StreamInterface $body */
        $body = $rawResponse->getBody();
        $buffer = '';
        $currentEvent = '';

        while (! $body->eof()) {
            $buffer .= $body->read(8192);

            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $raw = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                foreach (explode("\n", $raw) as $line) {
                    if (str_starts_with($line, 'event: ')) {
                        $currentEvent = substr($line, 7);
                    } elseif (str_starts_with($line, 'data: ')) {
                        $json = substr($line, 6);

                        if ($json === '[DONE]') {
                            return;
                        }

                        $data = json_decode($json, true);

                        if ($data !== null) {
                            yield ['event' => $currentEvent, 'data' => $data];
                        }
                    }
                }

                $currentEvent = '';
            }
        }
    }
}
