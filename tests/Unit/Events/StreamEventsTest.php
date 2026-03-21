<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Events\StreamChunkReceived;
use Atlasphp\Atlas\Events\StreamCompleted;
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
        ->and($event->broadcastOn())->toBe($channel);
});

it('StreamChunkReceived broadcastOn returns channel', function () {
    $channel = new Channel('stream.42');
    $event = new StreamChunkReceived(channel: $channel, text: 'data');

    expect($event->broadcastOn())->toBe($channel);
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
    $usage = ['prompt_tokens' => 10, 'completion_tokens' => 20];

    $event = new StreamCompleted(
        channel: $channel,
        text: 'Complete response',
        usage: $usage,
        finishReason: FinishReason::Stop,
    );

    expect($event->text)->toBe('Complete response')
        ->and($event->usage)->toBe($usage)
        ->and($event->finishReason)->toBe(FinishReason::Stop)
        ->and($event->broadcastOn())->toBe($channel);
});

it('StreamCompleted broadcastOn returns channel', function () {
    $channel = new Channel('stream.99');
    $event = new StreamCompleted(channel: $channel, text: 'done');

    expect($event->broadcastOn())->toBe($channel);
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
        ->and($event->broadcastOn())->toBe($channel);
});

it('StreamToolCallReceived broadcastOn returns channel', function () {
    $channel = new Channel('tools.42');
    $event = new StreamToolCallReceived(channel: $channel, toolCalls: []);

    expect($event->broadcastOn())->toBe($channel);
});

it('StreamToolCallReceived broadcastAs returns StreamToolCallReceived', function () {
    $event = new StreamToolCallReceived(channel: new Channel('test'), toolCalls: []);

    expect($event->broadcastAs())->toBe('StreamToolCallReceived');
});
