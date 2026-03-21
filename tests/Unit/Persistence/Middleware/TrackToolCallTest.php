<?php

declare(strict_types=1);

use Atlasphp\Atlas\Executor\ToolResult;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Middleware\ToolContext;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Middleware\TrackToolCall;
use Atlasphp\Atlas\Persistence\Models\ExecutionToolCall;
use Atlasphp\Atlas\Persistence\Services\ExecutionService;

function makeToolCall(): ToolCall
{
    return new ToolCall(id: 'call_123', name: 'lookup', arguments: ['id' => 1]);
}

function makeToolContext(): ToolContext
{
    return new ToolContext(toolCall: makeToolCall(), meta: []);
}

function makeToolResult(bool $isError = false): ToolResult
{
    return new ToolResult(
        toolCall: makeToolCall(),
        content: $isError ? 'Something went wrong' : '{"found": true}',
        isError: $isError,
    );
}

function makeServiceWithExecutionAndStep(): ExecutionService
{
    $service = new ExecutionService;
    $service->createExecution(
        provider: 'openai',
        model: 'gpt-5',
        type: ExecutionType::Text,
    );
    $service->beginExecution();
    $service->createStep();
    $service->beginStep();

    return $service;
}

it('skips when no active execution', function () {
    $service = new ExecutionService;
    $middleware = new TrackToolCall($service);

    $context = makeToolContext();
    $result = makeToolResult();

    $returned = $middleware->handle($context, fn () => $result);

    expect($returned)->toBe($result);
    expect(ExecutionToolCall::count())->toBe(0);
});

it('skips when no current step', function () {
    $service = new ExecutionService;
    $service->createExecution(
        provider: 'openai',
        model: 'gpt-5',
        type: ExecutionType::Text,
    );
    $service->beginExecution();
    // No step created

    $middleware = new TrackToolCall($service);

    $context = makeToolContext();
    $result = makeToolResult();

    $returned = $middleware->handle($context, fn () => $result);

    expect($returned)->toBe($result);
    expect(ExecutionToolCall::count())->toBe(0);
});

it('creates tool call and completes on success', function () {
    $service = makeServiceWithExecutionAndStep();
    $middleware = new TrackToolCall($service);

    $context = makeToolContext();
    $result = makeToolResult();

    $middleware->handle($context, fn () => $result);

    $record = ExecutionToolCall::latest('id')->first();

    expect($record)->not->toBeNull();
    expect($record->status)->toBe(ExecutionStatus::Completed);
    expect($record->name)->toBe('lookup');
    expect($record->tool_call_id)->toBe('call_123');
    expect($record->result)->toBe('{"found": true}');
});

it('marks tool call failed when result isError', function () {
    $service = makeServiceWithExecutionAndStep();
    $middleware = new TrackToolCall($service);

    $context = makeToolContext();
    $result = makeToolResult(isError: true);

    $middleware->handle($context, fn () => $result);

    $record = ExecutionToolCall::latest('id')->first();

    expect($record->status)->toBe(ExecutionStatus::Failed);
    expect($record->result)->toBe('Something went wrong');
});

it('marks tool call failed and re-throws on exception', function () {
    $service = makeServiceWithExecutionAndStep();
    $middleware = new TrackToolCall($service);

    $context = makeToolContext();

    try {
        $middleware->handle($context, function () {
            throw new RuntimeException('Tool crashed');
        });
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('Tool crashed');
    }

    $record = ExecutionToolCall::latest('id')->first();

    expect($record->status)->toBe(ExecutionStatus::Failed);
    expect($record->result)->toBe('Tool crashed');
});

it('records duration', function () {
    $service = makeServiceWithExecutionAndStep();
    $middleware = new TrackToolCall($service);

    $context = makeToolContext();
    $result = makeToolResult();

    $middleware->handle($context, fn () => $result);

    $record = ExecutionToolCall::latest('id')->first();

    expect($record->duration_ms)->not->toBeNull();
    expect($record->duration_ms)->toBeGreaterThanOrEqual(0);
});
