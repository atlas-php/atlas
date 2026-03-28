<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers;

use Generator;
use Psr\Http\Message\StreamInterface;

/**
 * Shared SSE (Server-Sent Events) parser for provider stream responses.
 *
 * Provides two parsing modes:
 * - parse(): Named-event SSE (event: + data: lines) — used by OpenAI and Anthropic
 * - parseDataOnly(): Data-only SSE (data: lines only) — used by Google and Chat Completions
 *
 * Both modes handle CRLF line endings, the [DONE] sentinel, and chunked reads.
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
            $buffer .= str_replace("\r\n", "\n", $body->read(8192));

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

    /**
     * Parse a data-only SSE stream (data: lines only, no named events).
     *
     * Used by providers that follow the Chat Completions streaming format
     * (Google Gemini, any /v1/chat/completions endpoint).
     *
     * @return Generator<int, array<string, mixed>>
     */
    public static function parseDataOnly(mixed $rawResponse): Generator
    {
        /** @var StreamInterface $body */
        $body = $rawResponse->getBody();
        $buffer = '';

        while (! $body->eof()) {
            $buffer .= str_replace("\r\n", "\n", $body->read(8192));

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if ($line === '' || $line === 'data: [DONE]') {
                    continue;
                }

                if (str_starts_with($line, 'data: ')) {
                    $json = json_decode(substr($line, 6), true);

                    if ($json !== null) {
                        yield $json;
                    }
                }
            }
        }

        // Process any remaining buffered data (no trailing newline)
        $line = trim($buffer);

        if ($line !== '' && $line !== 'data: [DONE]' && str_starts_with($line, 'data: ')) {
            $json = json_decode(substr($line, 6), true);

            if ($json !== null) {
                yield $json;
            }
        }
    }
}
