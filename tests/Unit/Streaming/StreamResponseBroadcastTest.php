<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Events\StreamChunkReceived;
use Atlasphp\Atlas\Events\StreamCompleted;
use Atlasphp\Atlas\Events\StreamToolCallReceived;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\Usage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
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
            && $event->usage['input_tokens'] === 10
            && $event->usage['output_tokens'] === 5;
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

it('broadcast events implement ShouldBroadcastNow', function () {
    $chunk = new StreamChunkReceived(new Channel('test'), 'text');
    $tool = new StreamToolCallReceived(new Channel('test'), []);
    $completed = new StreamCompleted(new Channel('test'), 'text');

    expect($chunk)->toBeInstanceOf(ShouldBroadcastNow::class);
    expect($tool)->toBeInstanceOf(ShouldBroadcastNow::class);
    expect($completed)->toBeInstanceOf(ShouldBroadcastNow::class);
});

it('broadcast events have correct broadcastAs names', function () {
    $chunk = new StreamChunkReceived(new Channel('test'), 'text');
    $tool = new StreamToolCallReceived(new Channel('test'), []);
    $completed = new StreamCompleted(new Channel('test'), 'text');

    expect($chunk->broadcastAs())->toBe('StreamChunkReceived');
    expect($tool->broadcastAs())->toBe('StreamToolCallReceived');
    expect($completed->broadcastAs())->toBe('StreamCompleted');
});

it('StreamChunkReceived broadcastOn returns the channel', function () {
    $channel = new Channel('chat.42');
    $event = new StreamChunkReceived($channel, 'hello');

    expect($event->broadcastOn())->toBe($channel);
});

it('StreamToolCallReceived broadcastOn returns the channel', function () {
    $channel = new Channel('chat.42');
    $event = new StreamToolCallReceived($channel, [['name' => 'search']]);

    expect($event->broadcastOn())->toBe($channel);
});

it('StreamCompleted broadcastOn returns the channel', function () {
    $channel = new Channel('chat.42');
    $event = new StreamCompleted($channel, 'done', ['input_tokens' => 10, 'output_tokens' => 5], FinishReason::Stop);

    expect($event->broadcastOn())->toBe($channel);
});
