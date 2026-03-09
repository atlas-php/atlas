<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Events\AgentStreamChunk;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

test('AgentStreamChunk implements ShouldBroadcast', function () {
    $event = new AgentStreamChunk(
        agentKey: 'test-agent',
        requestId: 'req-123',
        type: 'text_delta',
        delta: 'Hello',
    );

    expect($event)->toBeInstanceOf(ShouldBroadcast::class);
});

test('AgentStreamChunk broadcasts on private channel', function () {
    $event = new AgentStreamChunk(
        agentKey: 'test-agent',
        requestId: 'req-123',
        type: 'text_delta',
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
        type: 'text_delta',
    );

    expect($event->broadcastAs())->toBe('atlas.stream.chunk');
});

test('AgentStreamChunk broadcastWith includes type, delta, and metadata', function () {
    $event = new AgentStreamChunk(
        agentKey: 'test-agent',
        requestId: 'req-123',
        type: 'text_delta',
        delta: 'Hello world',
        metadata: ['id' => 'evt_1', 'delta' => 'Hello world', 'message_id' => 'msg_1'],
    );

    $data = $event->broadcastWith();

    expect($data['type'])->toBe('text_delta');
    expect($data['delta'])->toBe('Hello world');
    expect($data['metadata'])->toBe(['id' => 'evt_1', 'delta' => 'Hello world', 'message_id' => 'msg_1']);
});

test('AgentStreamChunk holds all properties', function () {
    $event = new AgentStreamChunk(
        agentKey: 'my-agent',
        requestId: 'req-456',
        type: 'stream_end',
        delta: null,
        metadata: ['finish_reason' => 'Stop', 'usage' => ['prompt_tokens' => 10]],
    );

    expect($event->agentKey)->toBe('my-agent');
    expect($event->requestId)->toBe('req-456');
    expect($event->type)->toBe('stream_end');
    expect($event->delta)->toBeNull();
    expect($event->metadata)->toBe(['finish_reason' => 'Stop', 'usage' => ['prompt_tokens' => 10]]);
});

test('AgentStreamChunk defaults metadata to empty array', function () {
    $event = new AgentStreamChunk(
        agentKey: 'test-agent',
        requestId: 'req-123',
        type: 'text_delta',
    );

    expect($event->metadata)->toBe([]);
});
