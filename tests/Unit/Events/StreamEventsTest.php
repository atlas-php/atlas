<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Events\StreamChunkReceived;
use Atlasphp\Atlas\Events\StreamCompleted;
use Atlasphp\Atlas\Events\StreamStarted;
use Atlasphp\Atlas\Events\StreamThinkingReceived;
use Atlasphp\Atlas\Events\StreamToolCallReceived;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

// ─── StreamChunkReceived ────────────────────────────────────────────────────

it('StreamChunkReceived implements ShouldBroadcastNow', function () {
    $event = new StreamChunkReceived(
        channel: new Channel('test'),
        text: 'Hello',
    );

    expect($event)->toBeInstanceOf(ShouldBroadcastNow::class);
});

it('StreamChunkReceived stores text and channel', function () {
    $channel = new PrivateChannel('chat.1');
    $event = new StreamChunkReceived(channel: $channel, text: 'chunk');

    expect($event->text)->toBe('chunk')
        ->and($event->broadcastOn())->toBe([$channel]);
});

it('StreamChunkReceived broadcastAs returns StreamChunkReceived', function () {
    $event = new StreamChunkReceived(channel: new Channel('test'), text: 'hi');

    expect($event->broadcastAs())->toBe('StreamChunkReceived');
});

// ─── StreamCompleted ────────────────────────────────────────────────────────

it('StreamCompleted implements ShouldBroadcastNow', function () {
    $event = new StreamCompleted(
        channel: new Channel('test'),
        text: 'Final text',
    );

    expect($event)->toBeInstanceOf(ShouldBroadcastNow::class);
});

it('StreamCompleted stores text, usage, finishReason, channel', function () {
    $channel = new PrivateChannel('chat.5');
    $usage = ['input_tokens' => 10, 'output_tokens' => 20];

    $event = new StreamCompleted(
        channel: $channel,
        text: 'Complete response',
        usage: $usage,
        finishReason: FinishReason::Stop,
    );

    expect($event->text)->toBe('Complete response')
        ->and($event->usage)->toBe($usage)
        ->and($event->finishReason)->toBe(FinishReason::Stop)
        ->and($event->broadcastOn())->toBe([$channel]);
});

it('StreamCompleted broadcastAs returns StreamCompleted', function () {
    $event = new StreamCompleted(channel: new Channel('test'), text: 'done');

    expect($event->broadcastAs())->toBe('StreamCompleted');
});

it('StreamCompleted defaults usage and finishReason to null', function () {
    $event = new StreamCompleted(channel: new Channel('test'), text: 'done');

    expect($event->usage)->toBeNull()
        ->and($event->finishReason)->toBeNull();
});

// ─── StreamToolCallReceived ─────────────────────────────────────────────────

it('StreamToolCallReceived implements ShouldBroadcastNow', function () {
    $event = new StreamToolCallReceived(
        channel: new Channel('test'),
        toolCalls: [],
    );

    expect($event)->toBeInstanceOf(ShouldBroadcastNow::class);
});

it('StreamToolCallReceived stores toolCalls and channel', function () {
    $channel = new PrivateChannel('chat.7');
    $toolCalls = [
        ['id' => 'call_1', 'name' => 'search', 'arguments' => '{"q":"test"}'],
    ];

    $event = new StreamToolCallReceived(channel: $channel, toolCalls: $toolCalls);

    expect($event->toolCalls)->toBe($toolCalls)
        ->and($event->broadcastOn())->toBe([$channel]);
});

it('StreamToolCallReceived broadcastAs returns StreamToolCallReceived', function () {
    $event = new StreamToolCallReceived(channel: new Channel('test'), toolCalls: []);

    expect($event->broadcastAs())->toBe('StreamToolCallReceived');
});

// ─── StreamStarted ─────────────────────────────────────────────────────────

it('StreamStarted implements ShouldBroadcastNow', function () {
    $event = new StreamStarted(channel: new Channel('test'));

    expect($event)->toBeInstanceOf(ShouldBroadcastNow::class);
});

it('StreamStarted broadcastOn returns channel when provided', function () {
    $channel = new PrivateChannel('stream.1');
    $event = new StreamStarted(channel: $channel);

    expect($event->broadcastOn())->toHaveCount(1)
        ->and($event->broadcastOn()[0])->toBe($channel);
});

it('StreamStarted broadcastOn returns empty when no channel', function () {
    $event = new StreamStarted;

    expect($event->broadcastOn())->toBe([]);
});

it('StreamStarted broadcastAs returns StreamStarted', function () {
    $event = new StreamStarted(channel: new Channel('test'));

    expect($event->broadcastAs())->toBe('StreamStarted');
});

it('StreamStarted broadcastWhen returns true with channel', function () {
    $channel = new PrivateChannel('test');
    $event = new StreamStarted(channel: $channel);

    expect($event->broadcastWhen())->toBeTrue();
});

it('StreamStarted broadcastWhen returns false without channel', function () {
    $event = new StreamStarted;

    expect($event->broadcastWhen())->toBeFalse();
});

// ─── StreamThinkingReceived ─────────────────────────────────────────────

it('StreamThinkingReceived implements ShouldBroadcastNow', function () {
    $event = new StreamThinkingReceived(
        channel: new Channel('test'),
        text: 'thinking...',
    );

    expect($event)->toBeInstanceOf(ShouldBroadcastNow::class);
});

it('StreamThinkingReceived stores text and channel', function () {
    $channel = new PrivateChannel('chat.1');
    $event = new StreamThinkingReceived(channel: $channel, text: 'Let me think...');

    expect($event->text)->toBe('Let me think...')
        ->and($event->broadcastOn())->toBe([$channel]);
});

it('StreamThinkingReceived broadcastAs returns StreamThinkingReceived', function () {
    $event = new StreamThinkingReceived(channel: new Channel('test'), text: 'hmm');

    expect($event->broadcastAs())->toBe('StreamThinkingReceived');
});
