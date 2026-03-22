<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Events\AgentCompleted;
use Atlasphp\Atlas\Events\AgentMaxStepsExceeded;
use Atlasphp\Atlas\Events\AgentStarted;
use Atlasphp\Atlas\Events\AgentStepCompleted;
use Atlasphp\Atlas\Events\AgentStepStarted;
use Atlasphp\Atlas\Events\AgentToolCallCompleted;
use Atlasphp\Atlas\Events\AgentToolCallFailed;
use Atlasphp\Atlas\Events\AgentToolCallStarted;
use Atlasphp\Atlas\Executor\Step;
use Atlasphp\Atlas\Executor\ToolResult;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Responses\Usage;

// ─── AgentStarted ──────────────────────────────────────────────────────────

it('AgentStarted stores agentKey, maxSteps, parallelToolCalls', function () {
    $event = new AgentStarted(
        agentKey: 'my-agent',
        maxSteps: 10,
        parallelToolCalls: true,
    );

    expect($event->agentKey)->toBe('my-agent')
        ->and($event->maxSteps)->toBe(10)
        ->and($event->parallelToolCalls)->toBeTrue();
});

it('AgentStarted accepts null agentKey and maxSteps', function () {
    $event = new AgentStarted(
        agentKey: null,
        maxSteps: null,
        parallelToolCalls: true,
    );

    expect($event->agentKey)->toBeNull()
        ->and($event->maxSteps)->toBeNull();
});

it('AgentStarted stores parallelToolCalls as false', function () {
    $event = new AgentStarted(
        agentKey: 'sequential-agent',
        maxSteps: 5,
        parallelToolCalls: false,
    );

    expect($event->parallelToolCalls)->toBeFalse();
});

// ─── AgentStepStarted ──────────────────────────────────────────────────────

it('AgentStepStarted stores stepNumber', function () {
    $event = new AgentStepStarted(stepNumber: 3);

    expect($event->stepNumber)->toBe(3);
});

// ─── AgentStepCompleted ────────────────────────────────────────────────────

it('AgentStepCompleted stores stepNumber, finishReason, usage', function () {
    $usage = new Usage(10, 20);

    $event = new AgentStepCompleted(
        stepNumber: 2,
        finishReason: FinishReason::Stop,
        usage: $usage,
    );

    expect($event->stepNumber)->toBe(2)
        ->and($event->finishReason)->toBe(FinishReason::Stop)
        ->and($event->usage)->toBe($usage);
});

it('AgentStepCompleted stores ToolCalls finish reason', function () {
    $usage = new Usage(15, 25);

    $event = new AgentStepCompleted(
        stepNumber: 1,
        finishReason: FinishReason::ToolCalls,
        usage: $usage,
    );

    expect($event->finishReason)->toBe(FinishReason::ToolCalls);
});

// ─── AgentMaxStepsExceeded ─────────────────────────────────────────────────

it('AgentMaxStepsExceeded stores limit and steps', function () {
    $step = new Step(text: 'hello', toolCalls: [], toolResults: [], usage: new Usage(10, 20));
    $steps = [$step];

    $event = new AgentMaxStepsExceeded(limit: 5, steps: $steps);

    expect($event->limit)->toBe(5)
        ->and($event->steps)->toBe($steps)
        ->and($event->steps)->toHaveCount(1);
});

it('AgentMaxStepsExceeded accepts empty steps array', function () {
    $event = new AgentMaxStepsExceeded(limit: 3, steps: []);

    expect($event->limit)->toBe(3)
        ->and($event->steps)->toBe([]);
});

// ─── AgentCompleted ────────────────────────────────────────────────────────

it('AgentCompleted stores steps', function () {
    $step = new Step(text: 'done', toolCalls: [], toolResults: [], usage: new Usage(10, 20));
    $steps = [$step];

    $event = new AgentCompleted(steps: $steps);

    expect($event->steps)->toBe($steps)
        ->and($event->steps)->toHaveCount(1);
});

it('AgentCompleted accepts empty steps array', function () {
    $event = new AgentCompleted(steps: []);

    expect($event->steps)->toBe([]);
});

// ─── AgentToolCallStarted ──────────────────────────────────────────────────

it('AgentToolCallStarted stores toolCall', function () {
    $toolCall = new ToolCall('tc-1', 'search', ['q' => 'test']);

    $event = new AgentToolCallStarted(toolCall: $toolCall);

    expect($event->toolCall)->toBe($toolCall)
        ->and($event->toolCall->id)->toBe('tc-1')
        ->and($event->toolCall->name)->toBe('search')
        ->and($event->toolCall->arguments)->toBe(['q' => 'test']);
});

// ─── AgentToolCallCompleted ────────────────────────────────────────────────

it('AgentToolCallCompleted stores toolCall and result', function () {
    $toolCall = new ToolCall('tc-1', 'search', ['q' => 'test']);
    $result = new ToolResult(toolCall: $toolCall, content: 'result', isError: false);

    $event = new AgentToolCallCompleted(toolCall: $toolCall, result: $result);

    expect($event->toolCall)->toBe($toolCall)
        ->and($event->result)->toBe($result)
        ->and($event->result->content)->toBe('result')
        ->and($event->result->isError)->toBeFalse();
});

it('AgentToolCallCompleted stores error result', function () {
    $toolCall = new ToolCall('tc-2', 'fetch', ['url' => 'http://example.com']);
    $result = new ToolResult(toolCall: $toolCall, content: 'Connection refused', isError: true);

    $event = new AgentToolCallCompleted(toolCall: $toolCall, result: $result);

    expect($event->result->isError)->toBeTrue()
        ->and($event->result->content)->toBe('Connection refused');
});

// ─── AgentToolCallFailed ───────────────────────────────────────────────────

it('AgentToolCallFailed stores toolCall and exception', function () {
    $toolCall = new ToolCall('tc-1', 'search', ['q' => 'test']);
    $exception = new RuntimeException('Tool execution failed');

    $event = new AgentToolCallFailed(toolCall: $toolCall, exception: $exception);

    expect($event->toolCall)->toBe($toolCall)
        ->and($event->exception)->toBe($exception)
        ->and($event->exception->getMessage())->toBe('Tool execution failed');
});

it('AgentToolCallFailed accepts any Throwable', function () {
    $toolCall = new ToolCall('tc-3', 'calculate', ['expr' => '1/0']);
    $error = new Error('Division by zero');

    $event = new AgentToolCallFailed(toolCall: $toolCall, exception: $error);

    expect($event->exception)->toBeInstanceOf(Throwable::class)
        ->and($event->exception->getMessage())->toBe('Division by zero');
});
