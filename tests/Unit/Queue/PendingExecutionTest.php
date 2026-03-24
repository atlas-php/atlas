<?php

declare(strict_types=1);

use Atlasphp\Atlas\Events\ExecutionQueued;
use Atlasphp\Atlas\Queue\Jobs\ExecuteAtlasJob;
use Atlasphp\Atlas\Queue\PendingExecution;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

it('stores execution ID', function () {
    Queue::fake();

    $job = new ExecuteAtlasJob(
        requestClass: 'Atlasphp\Atlas\Pending\TextRequest',
        terminal: 'asText',
        payload: [],
        executionId: 42,
    );

    $pending = new PendingExecution(
        executionId: 42,
        job: $job,
    );

    expect($pending->executionId)->toBe(42);

    // Dispatch to prevent __destruct from dispatching on a fake queue after teardown
    $pending->dispatch();
});

it('then() returns self for chaining', function () {
    Queue::fake();

    $job = new ExecuteAtlasJob(
        requestClass: 'Atlasphp\Atlas\Pending\TextRequest',
        terminal: 'asText',
        payload: [],
    );

    $pending = new PendingExecution(
        executionId: null,
        job: $job,
    );

    $result = $pending->then(function () {});

    expect($result)->toBe($pending);

    $pending->dispatch();
});

it('catch() returns self for chaining', function () {
    Queue::fake();

    $job = new ExecuteAtlasJob(
        requestClass: 'Atlasphp\Atlas\Pending\TextRequest',
        terminal: 'asText',
        payload: [],
    );

    $pending = new PendingExecution(
        executionId: null,
        job: $job,
    );

    $result = $pending->catch(function () {});

    expect($result)->toBe($pending);

    $pending->dispatch();
});

it('dispatch() dispatches the job', function () {
    Queue::fake();

    $job = new ExecuteAtlasJob(
        requestClass: 'Atlasphp\Atlas\Pending\TextRequest',
        terminal: 'asText',
        payload: [],
    );

    $pending = new PendingExecution(
        executionId: 1,
        job: $job,
    );

    $pending->dispatch();

    Queue::assertPushed(ExecuteAtlasJob::class);
});

it('dispatch() is idempotent', function () {
    Queue::fake();

    $job = new ExecuteAtlasJob(
        requestClass: 'Atlasphp\Atlas\Pending\TextRequest',
        terminal: 'asText',
        payload: [],
    );

    $pending = new PendingExecution(
        executionId: 1,
        job: $job,
    );

    $pending->dispatch();
    $pending->dispatch();

    Queue::assertPushed(ExecuteAtlasJob::class, 1);
});

it('dispatch() fires ExecutionQueued event', function () {
    Queue::fake();
    Event::fake([ExecutionQueued::class]);

    $job = new ExecuteAtlasJob(
        requestClass: 'Atlasphp\Atlas\Pending\TextRequest',
        terminal: 'asText',
        payload: [],
        executionId: 99,
    );

    $pending = new PendingExecution(
        executionId: 99,
        job: $job,
    );

    $pending->dispatch();

    Event::assertDispatched(ExecutionQueued::class, function (ExecutionQueued $event) {
        return $event->executionId === 99;
    });
});

it('dispatches lazily via __destruct', function () {
    Queue::fake();

    $job = new ExecuteAtlasJob(
        requestClass: 'Atlasphp\Atlas\Pending\TextRequest',
        terminal: 'asText',
        payload: [],
    );

    // Create PendingExecution in a scope — when it goes out of scope, __destruct fires
    $pending = new PendingExecution(
        executionId: null,
        job: $job,
    );

    // Manually trigger destruct by unsetting
    unset($pending);

    Queue::assertPushed(ExecuteAtlasJob::class);
});

it('__destruct reports exception instead of throwing when dispatch fails', function () {
    Queue::fake();

    $job = new ExecuteAtlasJob(
        requestClass: 'Atlasphp\Atlas\Pending\TextRequest',
        terminal: 'asText',
        payload: [],
    );

    // Create an anonymous subclass that forces dispatch() to throw
    $pending = new class(null, $job) extends PendingExecution
    {
        public function dispatch(): static
        {
            throw new RuntimeException('Queue connection failed');
        }
    };

    // __destruct should catch the exception and report it, not throw
    // If this test completes without an error, the catch branch is working
    unset($pending);

    expect(true)->toBeTrue();
});
