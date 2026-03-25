<?php

declare(strict_types=1);

use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\ExecutionStep;
use Atlasphp\Atlas\Persistence\Models\ExecutionToolCall;
use Atlasphp\Atlas\Persistence\Services\ExecutionService;
use Atlasphp\Atlas\Responses\Usage;

beforeEach(function () {
    $this->service = app(ExecutionService::class);
});

it('tracks full execution lifecycle: create → step → tool call → complete', function () {
    // ── Create and begin execution ──────────────────────────────
    $execution = $this->service->createExecution(
        provider: 'anthropic',
        model: 'claude-4',
        agent: 'research-bot',
    );

    expect($execution->status)->toBe(ExecutionStatus::Pending);

    $this->service->beginExecution();
    $execution->refresh();
    expect($execution->status)->toBe(ExecutionStatus::Processing)
        ->and($execution->started_at)->not->toBeNull();

    // ── Create and begin step ───────────────────────────────────
    $step = $this->service->createStep();
    expect($step->status)->toBe(ExecutionStatus::Pending)
        ->and($step->execution_id)->toBe($execution->id)
        ->and($step->sequence)->toBe(1);

    $this->service->beginStep();
    $step->refresh();
    expect($step->status)->toBe(ExecutionStatus::Processing);

    // ── Record provider response on step ────────────────────────
    $step->recordResponse(
        content: 'Let me search for that.',
        reasoning: null,
        finishReason: 'tool_calls',
    );
    $step->refresh();
    expect($step->content)->toBe('Let me search for that.')
        ->and($step->finish_reason)->toBe('tool_calls');

    // ── Create and execute tool call ────────────────────────────
    $toolCall = new ToolCall(
        id: 'call_search_1',
        name: 'web_search',
        arguments: ['query' => 'Laravel 12 release date'],
    );

    $toolRecord = $this->service->createToolCall($toolCall);
    expect($toolRecord->status)->toBe(ExecutionStatus::Pending)
        ->and($toolRecord->execution_id)->toBe($execution->id)
        ->and($toolRecord->step_id)->toBe($step->id)
        ->and($toolRecord->arguments)->toBe(['query' => 'Laravel 12 release date']);

    $startTime = $this->service->beginToolCall($toolRecord);
    $toolRecord->refresh();
    expect($toolRecord->status)->toBe(ExecutionStatus::Processing);

    $this->service->completeToolCall($toolRecord, $startTime, 'Laravel 12 was released in March 2025.');
    $toolRecord->refresh();
    expect($toolRecord->status)->toBe(ExecutionStatus::Completed)
        ->and($toolRecord->result)->toBe('Laravel 12 was released in March 2025.')
        ->and($toolRecord->duration_ms)->toBeGreaterThanOrEqual(0);

    // ── Complete step ───────────────────────────────────────────
    $this->service->completeStep();
    $step->refresh();
    expect($step->status)->toBe(ExecutionStatus::Completed)
        ->and($step->completed_at)->not->toBeNull();

    // ── Complete execution ──────────────────────────────────────
    $this->service->completeExecution(new Usage(inputTokens: 150, outputTokens: 30));
    $execution->refresh();
    expect($execution->status)->toBe(ExecutionStatus::Completed)
        ->and($execution->completed_at)->not->toBeNull()
        ->and($execution->duration_ms)->toBeGreaterThanOrEqual(0)
        ->and($execution->usage)->toBe(['input_tokens' => 150, 'output_tokens' => 30]);

    // ── Verify all records exist in database ────────────────────
    expect(Execution::count())->toBe(1)
        ->and(ExecutionStep::count())->toBe(1)
        ->and(ExecutionToolCall::count())->toBe(1);
});

it('tracks multi-step execution with multiple tool calls', function () {
    $this->service->createExecution(provider: 'openai', model: 'gpt-5');
    $this->service->beginExecution();

    // ── Step 1: tool call step ──────────────────────────────────
    $step1 = $this->service->createStep();
    $this->service->beginStep();

    $step1->recordResponse(
        content: 'Searching...',
        reasoning: null,
        finishReason: 'tool_calls',
    );

    $tc = new ToolCall(id: 'call_1', name: 'search', arguments: ['q' => 'test']);
    $record = $this->service->createToolCall($tc);
    $start = $this->service->beginToolCall($record);
    $this->service->completeToolCall($record, $start, 'result data');

    $this->service->completeStep();

    // ── Step 2: final response ──────────────────────────────────
    $step2 = $this->service->createStep();
    $this->service->beginStep();

    $step2->recordResponse(
        content: 'Here are the results.',
        reasoning: null,
        finishReason: 'stop',
    );

    $this->service->completeStep();

    // ── Complete execution ──────────────────────────────────────
    $this->service->completeExecution(new Usage(inputTokens: 300, outputTokens: 70));

    $execution = $this->service->getExecution();
    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Completed)
        ->and($execution->usage)->toBe(['input_tokens' => 300, 'output_tokens' => 70]);

    expect(ExecutionStep::count())->toBe(2)
        ->and(ExecutionToolCall::count())->toBe(1);
});

it('handles failure path: marks execution and in-flight step as failed', function () {
    $this->service->createExecution(provider: 'openai', model: 'gpt-5');
    $this->service->beginExecution();

    $step = $this->service->createStep();
    $this->service->beginStep();

    // Simulate failure during provider call
    $exception = new RuntimeException('API rate limit exceeded');
    $this->service->failExecution($exception);

    $execution = $this->service->getExecution();
    $execution->refresh();
    $step->refresh();

    // Execution should be failed
    expect($execution->status)->toBe(ExecutionStatus::Failed)
        ->and($execution->error)->toContain('RuntimeException')
        ->and($execution->error)->toContain('API rate limit exceeded')
        ->and($execution->completed_at)->not->toBeNull()
        ->and($execution->duration_ms)->toBeGreaterThanOrEqual(0);

    // In-flight step should also be failed
    expect($step->status)->toBe(ExecutionStatus::Failed)
        ->and($step->error)->toBe('API rate limit exceeded')
        ->and($step->completed_at)->not->toBeNull();
});

it('handles failure when step is pending (not yet processing)', function () {
    $this->service->createExecution(provider: 'openai', model: 'gpt-5');
    $this->service->beginExecution();

    $step = $this->service->createStep();
    // Step is pending — not begun yet

    $this->service->failExecution(new RuntimeException('Crash'));

    $execution = $this->service->getExecution();
    $execution->refresh();
    $step->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Failed);

    // Pending step should NOT be marked failed (only processing steps are)
    expect($step->status)->toBe(ExecutionStatus::Pending);
});

it('reset allows service reuse for another execution', function () {
    $this->service->createExecution(provider: 'openai', model: 'gpt-5');
    $this->service->beginExecution();
    $this->service->completeExecution();

    $this->service->reset();

    expect($this->service->hasActiveExecution())->toBeFalse()
        ->and($this->service->getExecution())->toBeNull();

    // Can create a new execution
    $newExecution = $this->service->createExecution(provider: 'anthropic', model: 'claude-4');

    expect($newExecution->provider)->toBe('anthropic')
        ->and($this->service->hasActiveExecution())->toBeTrue();
});
