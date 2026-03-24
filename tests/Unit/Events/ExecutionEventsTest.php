<?php

declare(strict_types=1);

use Atlasphp\Atlas\Events\ExecutionCompleted;
use Atlasphp\Atlas\Events\ExecutionFailed;
use Atlasphp\Atlas\Events\ExecutionProcessing;
use Atlasphp\Atlas\Events\ExecutionQueued;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

// ─── ExecutionQueued ────────────────────────────────────────────────────────

it('ExecutionQueued implements ShouldBroadcastNow', function () {
    $event = new ExecutionQueued(executionId: 1);

    expect($event)->toBeInstanceOf(ShouldBroadcastNow::class);
});

it('ExecutionQueued stores executionId', function () {
    $event = new ExecutionQueued(executionId: 42);

    expect($event->executionId)->toBe(42);
});

it('ExecutionQueued accepts null executionId', function () {
    $event = new ExecutionQueued(executionId: null);

    expect($event->executionId)->toBeNull();
});

it('ExecutionQueued broadcastOn returns channel when provided', function () {
    $channel = new PrivateChannel('test-channel');
    $event = new ExecutionQueued(executionId: 1, channel: $channel);

    expect($event->broadcastOn())->toHaveCount(1);
    expect($event->broadcastOn()[0])->toBe($channel);
});

it('ExecutionQueued broadcastOn returns empty when no channel', function () {
    $event = new ExecutionQueued(executionId: 1);

    expect($event->broadcastOn())->toBe([]);
});

it('ExecutionQueued broadcastAs returns correct event name', function () {
    $event = new ExecutionQueued(executionId: 1);

    expect($event->broadcastAs())->toBe('ExecutionQueued');
});

it('ExecutionQueued broadcastWhen returns true with channel', function () {
    $channel = new PrivateChannel('test');
    $event = new ExecutionQueued(executionId: 1, channel: $channel);

    expect($event->broadcastWhen())->toBeTrue();
});

it('ExecutionQueued broadcastWhen returns false without channel', function () {
    $event = new ExecutionQueued(executionId: 1);

    expect($event->broadcastWhen())->toBeFalse();
});

// ─── ExecutionProcessing ───────────────────────────────────────────────────

it('ExecutionProcessing implements ShouldBroadcastNow', function () {
    $event = new ExecutionProcessing(executionId: 1);

    expect($event)->toBeInstanceOf(ShouldBroadcastNow::class);
});

it('ExecutionProcessing stores executionId', function () {
    $event = new ExecutionProcessing(executionId: 55);

    expect($event->executionId)->toBe(55);
});

it('ExecutionProcessing broadcastAs returns correct event name', function () {
    $event = new ExecutionProcessing(executionId: 1);

    expect($event->broadcastAs())->toBe('ExecutionProcessing');
});

it('ExecutionProcessing broadcastOn returns channel when provided', function () {
    $channel = new PrivateChannel('chat.1');
    $event = new ExecutionProcessing(executionId: 1, channel: $channel);

    expect($event->broadcastOn())->toHaveCount(1)
        ->and($event->broadcastOn()[0])->toBe($channel);
});

it('ExecutionProcessing broadcastWhen returns false without channel', function () {
    $event = new ExecutionProcessing(executionId: 1);

    expect($event->broadcastWhen())->toBeFalse()
        ->and($event->broadcastOn())->toBe([]);
});

// ─── ExecutionCompleted ─────────────────────────────────────────────────────

it('ExecutionCompleted implements ShouldBroadcastNow', function () {
    $event = new ExecutionCompleted(executionId: 1);

    expect($event)->toBeInstanceOf(ShouldBroadcastNow::class);
});

it('ExecutionCompleted stores executionId', function () {
    $event = new ExecutionCompleted(executionId: 99);

    expect($event->executionId)->toBe(99);
});

it('ExecutionCompleted accepts null executionId', function () {
    $event = new ExecutionCompleted(executionId: null);

    expect($event->executionId)->toBeNull();
});

it('ExecutionCompleted broadcastOn returns channel when provided', function () {
    $channel = new PrivateChannel('chat.123');
    $event = new ExecutionCompleted(executionId: 1, channel: $channel);

    expect($event->broadcastOn())->toHaveCount(1);
    expect($event->broadcastOn()[0])->toBe($channel);
});

it('ExecutionCompleted broadcastOn returns empty when no channel', function () {
    $event = new ExecutionCompleted(executionId: 1);

    expect($event->broadcastOn())->toBe([]);
});

it('ExecutionCompleted broadcastAs returns correct event name', function () {
    $event = new ExecutionCompleted(executionId: 1);

    expect($event->broadcastAs())->toBe('ExecutionCompleted');
});

it('ExecutionCompleted broadcastWhen returns true with channel', function () {
    $channel = new PrivateChannel('test');
    $event = new ExecutionCompleted(executionId: 1, channel: $channel);

    expect($event->broadcastWhen())->toBeTrue();
});

it('ExecutionCompleted broadcastWhen returns false without channel', function () {
    $event = new ExecutionCompleted(executionId: 1);

    expect($event->broadcastWhen())->toBeFalse();
});

// ─── ExecutionFailed ────────────────────────────────────────────────────────

it('ExecutionFailed implements ShouldBroadcastNow', function () {
    $event = new ExecutionFailed(executionId: 1, error: 'Something broke');

    expect($event)->toBeInstanceOf(ShouldBroadcastNow::class);
});

it('ExecutionFailed stores executionId and error', function () {
    $event = new ExecutionFailed(executionId: 77, error: 'Provider timeout');

    expect($event->executionId)->toBe(77);
    expect($event->error)->toBe('Provider timeout');
});

it('ExecutionFailed accepts null executionId', function () {
    $event = new ExecutionFailed(executionId: null, error: 'No tracking');

    expect($event->executionId)->toBeNull();
    expect($event->error)->toBe('No tracking');
});

it('ExecutionFailed broadcastOn returns channel when provided', function () {
    $channel = new PrivateChannel('errors.456');
    $event = new ExecutionFailed(executionId: 1, error: 'fail', channel: $channel);

    expect($event->broadcastOn())->toHaveCount(1);
    expect($event->broadcastOn()[0])->toBe($channel);
});

it('ExecutionFailed broadcastOn returns empty when no channel', function () {
    $event = new ExecutionFailed(executionId: 1, error: 'fail');

    expect($event->broadcastOn())->toBe([]);
});

it('ExecutionFailed broadcastAs returns correct event name', function () {
    $event = new ExecutionFailed(executionId: 1, error: 'fail');

    expect($event->broadcastAs())->toBe('ExecutionFailed');
});

it('ExecutionFailed broadcastWhen returns true with channel', function () {
    $channel = new PrivateChannel('test');
    $event = new ExecutionFailed(executionId: 1, error: 'fail', channel: $channel);

    expect($event->broadcastWhen())->toBeTrue();
});

it('ExecutionFailed broadcastWhen returns false without channel', function () {
    $event = new ExecutionFailed(executionId: 1, error: 'fail');

    expect($event->broadcastWhen())->toBeFalse();
});

// ─── Context params: provider, model, agentKey, traceId ──────────────────

it('ExecutionQueued stores context when passed', function () {
    $event = new ExecutionQueued(
        executionId: 1,
        provider: 'openai',
        model: 'gpt-4o',
        agentKey: 'my-agent',
        traceId: 'trace-001',
    );

    expect($event->provider)->toBe('openai')
        ->and($event->model)->toBe('gpt-4o')
        ->and($event->agentKey)->toBe('my-agent')
        ->and($event->traceId)->toBe('trace-001');
});

it('ExecutionQueued context params default to null', function () {
    $event = new ExecutionQueued(executionId: 1);

    expect($event->provider)->toBeNull()
        ->and($event->model)->toBeNull()
        ->and($event->agentKey)->toBeNull()
        ->and($event->traceId)->toBeNull();
});

it('ExecutionProcessing stores context when passed', function () {
    $event = new ExecutionProcessing(
        executionId: 2,
        provider: 'anthropic',
        model: 'claude-4',
        agentKey: 'proc-agent',
        traceId: 'trace-002',
    );

    expect($event->provider)->toBe('anthropic')
        ->and($event->model)->toBe('claude-4')
        ->and($event->agentKey)->toBe('proc-agent')
        ->and($event->traceId)->toBe('trace-002');
});

it('ExecutionProcessing context params default to null', function () {
    $event = new ExecutionProcessing(executionId: 2);

    expect($event->provider)->toBeNull()
        ->and($event->model)->toBeNull()
        ->and($event->agentKey)->toBeNull()
        ->and($event->traceId)->toBeNull();
});

it('ExecutionCompleted stores context when passed', function () {
    $event = new ExecutionCompleted(
        executionId: 3,
        provider: 'openai',
        model: 'gpt-4o',
        agentKey: 'done-agent',
        traceId: 'trace-003',
    );

    expect($event->provider)->toBe('openai')
        ->and($event->model)->toBe('gpt-4o')
        ->and($event->agentKey)->toBe('done-agent')
        ->and($event->traceId)->toBe('trace-003');
});

it('ExecutionCompleted context params default to null', function () {
    $event = new ExecutionCompleted(executionId: 3);

    expect($event->provider)->toBeNull()
        ->and($event->model)->toBeNull()
        ->and($event->agentKey)->toBeNull()
        ->and($event->traceId)->toBeNull();
});

it('ExecutionFailed stores context when passed', function () {
    $event = new ExecutionFailed(
        executionId: 4,
        error: 'timeout',
        provider: 'anthropic',
        model: 'claude-4',
        agentKey: 'fail-agent',
        traceId: 'trace-004',
    );

    expect($event->provider)->toBe('anthropic')
        ->and($event->model)->toBe('claude-4')
        ->and($event->agentKey)->toBe('fail-agent')
        ->and($event->traceId)->toBe('trace-004');
});

it('ExecutionFailed context params default to null', function () {
    $event = new ExecutionFailed(executionId: 4, error: 'fail');

    expect($event->provider)->toBeNull()
        ->and($event->model)->toBeNull()
        ->and($event->agentKey)->toBeNull()
        ->and($event->traceId)->toBeNull();
});

it('ExecutionFailed passes context through to parent', function () {
    $channel = new PrivateChannel('test');
    $event = new ExecutionFailed(
        executionId: 5,
        error: 'broke',
        channel: $channel,
        provider: 'openai',
        model: 'gpt-4o',
        agentKey: 'parent-agent',
        traceId: 'trace-005',
    );

    expect($event->executionId)->toBe(5)
        ->and($event->error)->toBe('broke')
        ->and($event->broadcastWhen())->toBeTrue()
        ->and($event->provider)->toBe('openai')
        ->and($event->model)->toBe('gpt-4o')
        ->and($event->agentKey)->toBe('parent-agent')
        ->and($event->traceId)->toBe('trace-005');
});
