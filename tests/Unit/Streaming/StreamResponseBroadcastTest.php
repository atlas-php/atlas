<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Events\StreamChunkReceived;
use Atlasphp\Atlas\Events\StreamCompleted;
use Atlasphp\Atlas\Events\StreamStarted;
use Atlasphp\Atlas\Events\StreamThinkingReceived;
use Atlasphp\Atlas\Events\StreamToolCallReceived;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\Usage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Support\Facades\Event;

it('broadcastOn returns $this for chaining', function () {
    $stream = new StreamResponse((function () {
        yield from [];
    })());

    $result = $stream->broadcastOn(new Channel('test'));

    expect($result)->toBe($stream);
});

it('broadcasts StreamChunkReceived for text chunks', function () {
    Event::fake();

    $stream = new StreamResponse((function () {
        yield new StreamChunk(ChunkType::Text, text: 'Hello');
        yield new StreamChunk(ChunkType::Done);
    })());

    $stream->broadcastOn(new Channel('chat.1'));

    foreach ($stream as $chunk) {
        // consume
    }

    Event::assertDispatched(StreamChunkReceived::class, function ($event) {
        return $event->text === 'Hello';
    });
});

it('broadcasts StreamToolCallReceived for tool call chunks', function () {
    Event::fake();

    $stream = new StreamResponse((function () {
        yield new StreamChunk(
            type: ChunkType::ToolCall,
            toolCalls: [new ToolCall('tc-1', 'search', ['q' => 'test'])],
        );
        yield new StreamChunk(ChunkType::Done);
    })());

    $stream->broadcastOn(new Channel('chat.1'));

    foreach ($stream as $chunk) {
        // consume
    }

    Event::assertDispatched(StreamToolCallReceived::class, function ($event) {
        return $event->toolCalls[0]['name'] === 'search';
    });
});

it('broadcasts StreamThinkingReceived for thinking chunks', function () {
    Event::fake();

    $stream = new StreamResponse((function () {
        yield new StreamChunk(ChunkType::Thinking, reasoning: 'Let me reason...');
        yield new StreamChunk(ChunkType::Text, text: 'Answer');
        yield new StreamChunk(ChunkType::Done);
    })());

    $stream->broadcastOn(new Channel('chat.1'));

    foreach ($stream as $chunk) {
        // consume
    }

    Event::assertDispatched(StreamThinkingReceived::class, function ($event) {
        return $event->text === 'Let me reason...';
    });
});

it('broadcasts StreamCompleted after last chunk with text and usage', function () {
    Event::fake();

    $stream = new StreamResponse((function () {
        yield new StreamChunk(ChunkType::Text, text: 'Hi');
        yield new StreamChunk(ChunkType::Done, usage: new Usage(10, 5), finishReason: FinishReason::Stop);
    })());

    $stream->broadcastOn(new Channel('chat.1'));

    foreach ($stream as $chunk) {
        // consume
    }

    Event::assertDispatched(StreamCompleted::class, function ($event) {
        return $event->text === 'Hi'
            && $event->usage['inputTokens'] === 10
            && $event->usage['outputTokens'] === 5;
    });
});

it('does not broadcast when broadcastOn not called', function () {
    Event::fake();

    $stream = new StreamResponse((function () {
        yield new StreamChunk(ChunkType::Text, text: 'Hello');
        yield new StreamChunk(ChunkType::Done);
    })());

    foreach ($stream as $chunk) {
        // consume
    }

    Event::assertNotDispatched(StreamChunkReceived::class);
    Event::assertNotDispatched(StreamCompleted::class);
});

// Event unit tests (ShouldBroadcastNow, broadcastAs, broadcastOn) live in StreamEventsTest.

// ─── StreamStarted broadcast ────────────────────────────────────────────────

it('broadcasts StreamStarted before chunks when channel is set', function () {
    Event::fake();

    $stream = new StreamResponse((function () {
        yield new StreamChunk(ChunkType::Text, text: 'Hello');
        yield new StreamChunk(ChunkType::Done);
    })());

    $stream->broadcastOn(new Channel('chat.1'));

    foreach ($stream as $chunk) {
        // consume
    }

    Event::assertDispatched(StreamStarted::class);
});

it('does not broadcast StreamStarted when no channel set', function () {
    Event::fake();

    $stream = new StreamResponse((function () {
        yield new StreamChunk(ChunkType::Text, text: 'Hello');
        yield new StreamChunk(ChunkType::Done);
    })());

    foreach ($stream as $chunk) {
        // consume
    }

    Event::assertNotDispatched(StreamStarted::class);
});

it('broadcasts StreamCompleted with error on exception', function () {
    Event::fake();

    $chunks = (function () {
        yield new StreamChunk(type: ChunkType::Text, text: 'partial');
        throw new RuntimeException('stream broke');
    })();

    $stream = new StreamResponse($chunks);
    $stream->broadcastOn(new Channel('test-error'));

    try {
        foreach ($stream as $chunk) {
            // consume
        }
    } catch (RuntimeException) {
        // expected
    }

    Event::assertDispatched(StreamCompleted::class, function ($e) {
        return $e->error === 'stream broke'
            && $e->text === 'partial'
            && $e->usage === null;
    });
});
