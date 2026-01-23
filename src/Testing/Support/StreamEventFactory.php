<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing\Support;

use Atlasphp\Atlas\Streaming\Events\StreamEndEvent;
use Atlasphp\Atlas\Streaming\Events\StreamStartEvent;
use Atlasphp\Atlas\Streaming\Events\TextDeltaEvent;
use Atlasphp\Atlas\Streaming\Events\ToolCallEndEvent;
use Atlasphp\Atlas\Streaming\Events\ToolCallStartEvent;
use Atlasphp\Atlas\Streaming\StreamEvent;
use Atlasphp\Atlas\Streaming\StreamResponse;
use Generator;

/**
 * Factory for creating fake streaming events in tests.
 *
 * Provides convenient methods for generating stream events and responses.
 */
final class StreamEventFactory
{
    /**
     * Create a StreamResponse from a text string.
     *
     * Splits the text into chunks and generates appropriate stream events.
     *
     * @param  string  $text  The complete text to stream.
     * @param  int  $chunkSize  Size of each text chunk.
     */
    public static function fromText(string $text, int $chunkSize = 10): StreamResponse
    {
        return new StreamResponse(self::createTextGenerator($text, $chunkSize));
    }

    /**
     * Create a StreamResponse with a tool call.
     *
     * @param  string  $toolName  The name of the tool.
     * @param  array<string, mixed>  $arguments  The tool arguments.
     * @param  string  $result  The tool result.
     * @param  string|null  $textAfter  Optional text after the tool call.
     */
    public static function withToolCall(
        string $toolName,
        array $arguments,
        string $result,
        ?string $textAfter = null,
    ): StreamResponse {
        return new StreamResponse(self::createToolCallGenerator($toolName, $arguments, $result, $textAfter));
    }

    /**
     * Create a StreamResponse from an array of text deltas.
     *
     * @param  array<int, string>  $deltas  The text deltas to stream.
     */
    public static function fromDeltas(array $deltas): StreamResponse
    {
        return new StreamResponse(self::createDeltasGenerator($deltas));
    }

    /**
     * Create a StreamResponse from an array of events.
     *
     * @param  array<int, StreamEvent>  $events  The events to stream.
     */
    public static function fromEvents(array $events): StreamResponse
    {
        return new StreamResponse(self::createEventsGenerator($events));
    }

    /**
     * @return Generator<int, StreamEvent>
     */
    private static function createTextGenerator(string $text, int $chunkSize): Generator
    {
        $timestamp = time();
        $eventId = 0;

        // Stream start
        yield new StreamStartEvent(
            id: 'evt_'.($eventId++),
            timestamp: $timestamp,
            model: 'fake-model',
            provider: 'fake',
        );

        // Text deltas
        $chunks = str_split($text, $chunkSize);
        foreach ($chunks as $chunk) {
            yield new TextDeltaEvent(
                id: 'evt_'.($eventId++),
                timestamp: $timestamp,
                text: $chunk,
            );
        }

        // Stream end
        yield new StreamEndEvent(
            id: 'evt_'.($eventId++),
            timestamp: $timestamp,
            finishReason: 'stop',
            usage: [
                'prompt_tokens' => 10,
                'completion_tokens' => (int) ceil(strlen($text) / 4),
                'total_tokens' => 10 + (int) ceil(strlen($text) / 4),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return Generator<int, StreamEvent>
     */
    private static function createToolCallGenerator(
        string $toolName,
        array $arguments,
        string $result,
        ?string $textAfter,
    ): Generator {
        $timestamp = time();
        $eventId = 0;
        $toolId = 'call_'.uniqid();

        // Stream start
        yield new StreamStartEvent(
            id: 'evt_'.($eventId++),
            timestamp: $timestamp,
            model: 'fake-model',
            provider: 'fake',
        );

        // Tool call start
        yield new ToolCallStartEvent(
            id: 'evt_'.($eventId++),
            timestamp: $timestamp,
            toolId: $toolId,
            toolName: $toolName,
            arguments: $arguments,
        );

        // Tool call end
        yield new ToolCallEndEvent(
            id: 'evt_'.($eventId++),
            timestamp: $timestamp,
            toolId: $toolId,
            toolName: $toolName,
            result: $result,
            success: true,
        );

        // Optional text after tool call
        if ($textAfter !== null) {
            $chunks = str_split($textAfter, 10);
            foreach ($chunks as $chunk) {
                yield new TextDeltaEvent(
                    id: 'evt_'.($eventId++),
                    timestamp: $timestamp,
                    text: $chunk,
                );
            }
        }

        // Stream end
        yield new StreamEndEvent(
            id: 'evt_'.($eventId++),
            timestamp: $timestamp,
            finishReason: 'stop',
        );
    }

    /**
     * @param  array<int, string>  $deltas
     * @return Generator<int, StreamEvent>
     */
    private static function createDeltasGenerator(array $deltas): Generator
    {
        $timestamp = time();
        $eventId = 0;

        // Stream start
        yield new StreamStartEvent(
            id: 'evt_'.($eventId++),
            timestamp: $timestamp,
            model: 'fake-model',
            provider: 'fake',
        );

        // Text deltas
        foreach ($deltas as $delta) {
            yield new TextDeltaEvent(
                id: 'evt_'.($eventId++),
                timestamp: $timestamp,
                text: $delta,
            );
        }

        // Stream end
        yield new StreamEndEvent(
            id: 'evt_'.($eventId++),
            timestamp: $timestamp,
            finishReason: 'stop',
        );
    }

    /**
     * @param  array<int, StreamEvent>  $events
     * @return Generator<int, StreamEvent>
     */
    private static function createEventsGenerator(array $events): Generator
    {
        foreach ($events as $event) {
            yield $event;
        }
    }
}
