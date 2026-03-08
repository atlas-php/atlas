<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Jobs\InvokeAgent;
use Atlasphp\Atlas\Agents\Support\QueuedAgentResponse;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

test('QueuedAgentResponse provides fluent API for queue configuration', function () {
    $job = new InvokeAgent('test-agent', 'Hello', []);
    $queued = new QueuedAgentResponse($job);

    $result = $queued->onQueue('ai');
    expect($result)->toBe($queued);
    expect($job->queue)->toBe('ai');
});

test('QueuedAgentResponse provides fluent API for connection configuration', function () {
    $job = new InvokeAgent('test-agent', 'Hello', []);
    $queued = new QueuedAgentResponse($job);

    $result = $queued->onConnection('redis');
    expect($result)->toBe($queued);
    expect($job->connection)->toBe('redis');
});

test('QueuedAgentResponse then() configures success callback', function () {
    $job = new InvokeAgent('test-agent', 'Hello', []);
    $queued = new QueuedAgentResponse($job);

    $result = $queued->then(fn ($response) => null);
    expect($result)->toBe($queued);
    expect($job->thenCallback)->not->toBeNull();
});

test('QueuedAgentResponse catch() configures failure callback', function () {
    $job = new InvokeAgent('test-agent', 'Hello', []);
    $queued = new QueuedAgentResponse($job);

    $result = $queued->catch(fn ($e) => null);
    expect($result)->toBe($queued);
    expect($job->catchCallback)->not->toBeNull();
});

test('QueuedAgentResponse delay() configures job delay', function () {
    $job = new InvokeAgent('test-agent', 'Hello', []);
    $queued = new QueuedAgentResponse($job);

    $result = $queued->delay(30);
    expect($result)->toBe($queued);
    expect($job->delay)->toBe(30);
});

test('QueuedAgentResponse delay() accepts DateInterval', function () {
    $job = new InvokeAgent('test-agent', 'Hello', []);
    $queued = new QueuedAgentResponse($job);

    $interval = new DateInterval('PT5M');
    $result = $queued->delay($interval);
    expect($result)->toBe($queued);
    expect($job->delay)->toBe($interval);
});

test('QueuedAgentResponse getJob() returns underlying job', function () {
    $job = new InvokeAgent('test-agent', 'Hello', []);
    $queued = new QueuedAgentResponse($job);

    expect($queued->getJob())->toBe($job);
});
