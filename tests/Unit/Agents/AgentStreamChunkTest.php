<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Events\AgentStreamChunk;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

test('AgentStreamChunk implements ShouldBroadcast', function () {
    $event = new AgentStreamChunk(
        agentKey: 'test-agent',
        requestId: 'req-123',
        type: 'text-delta',
        delta: 'Hello',
    );

    expect($event)->toBeInstanceOf(ShouldBroadcast::class);
});

test('AgentStreamChunk broadcasts on private channel', function () {
    $event = new AgentStreamChunk(
        agentKey: 'test-agent',
        requestId: 'req-123',
        type: 'text-delta',
        delta: 'Hello',
    );

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe('private-atlas.agent.test-agent.req-123');
});

test('AgentStreamChunk broadcasts as atlas.stream.chunk', function () {
    $event = new AgentStreamChunk(
        agentKey: 'test-agent',
        requestId: 'req-123',
        type: 'text-delta',
    );

    expect($event->broadcastAs())->toBe('atlas.stream.chunk');
});

test('AgentStreamChunk broadcastWith includes type and delta', function () {
    $event = new AgentStreamChunk(
        agentKey: 'test-agent',
        requestId: 'req-123',
        type: 'text-delta',
        delta: 'Hello world',
        metadata: ['model' => 'gpt-4'],
    );

    $data = $event->broadcastWith();

    expect($data['type'])->toBe('text-delta');
    expect($data['delta'])->toBe('Hello world');
    expect($data['metadata'])->toBe(['model' => 'gpt-4']);
});

test('AgentStreamChunk holds all properties', function () {
    $event = new AgentStreamChunk(
        agentKey: 'my-agent',
        requestId: 'req-456',
        type: 'stream-end',
        delta: null,
        metadata: ['finish_reason' => 'stop'],
    );

    expect($event->agentKey)->toBe('my-agent');
    expect($event->requestId)->toBe('req-456');
    expect($event->type)->toBe('stream-end');
    expect($event->delta)->toBeNull();
    expect($event->metadata)->toBe(['finish_reason' => 'stop']);
});
