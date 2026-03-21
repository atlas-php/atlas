<?php

declare(strict_types=1);

use Atlasphp\Atlas\Events\ExecutionCompleted;
use Atlasphp\Atlas\Events\ExecutionFailed;
use Atlasphp\Atlas\Events\ExecutionQueued;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

// ─── ExecutionQueued ────────────────────────────────────────────────────────

it('ExecutionQueued implements ShouldBroadcastNow', function () {
    $event = new ExecutionQueued(executionId: 1);

    expect($event)->toBeInstanceOf(ShouldBroadcastNow::class);
});

it('ExecutionQueued stores executionId and meta', function () {
    $event = new ExecutionQueued(executionId: 42, meta: ['key' => 'value']);

    expect($event->executionId)->toBe(42);
    expect($event->meta)->toBe(['key' => 'value']);
});

it('ExecutionQueued accepts null executionId', function () {
    $event = new ExecutionQueued(executionId: null);

    expect($event->executionId)->toBeNull();
    expect($event->meta)->toBe([]);
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
