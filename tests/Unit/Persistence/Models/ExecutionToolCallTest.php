<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\ExecutionStep;
use Atlasphp\Atlas\Persistence\Models\ExecutionToolCall;

it('creates in pending status via factory', function () {
    $toolCall = ExecutionToolCall::factory()->create();

    expect($toolCall->exists)->toBeTrue()
        ->and($toolCall->status)->toBe(ExecutionStatus::Pending);
});

it('markCompleted sets result and duration', function () {
    $toolCall = ExecutionToolCall::factory()->create();

    $toolCall->markCompleted('{"data": "found"}', 250);

    $toolCall->refresh();

    expect($toolCall->status)->toBe(ExecutionStatus::Completed)
        ->and($toolCall->result)->toBe('{"data": "found"}')
        ->and($toolCall->completed_at)->not->toBeNull()
        ->and($toolCall->duration_ms)->toBe(250);
});

it('markFailed sets error', function () {
    $toolCall = ExecutionToolCall::factory()->create();

    $toolCall->markFailed('Tool not found', 100);

    $toolCall->refresh();

    expect($toolCall->status)->toBe(ExecutionStatus::Failed)
        ->and($toolCall->result)->toBe('Tool not found')
        ->and($toolCall->completed_at)->not->toBeNull()
        ->and($toolCall->duration_ms)->toBe(100);
});

it('scopeForTool filters by name', function () {
    $execution = Execution::factory()->create();
    $step = ExecutionStep::factory()->create(['execution_id' => $execution->id]);

    ExecutionToolCall::factory()->withName('search')->create([
        'execution_id' => $execution->id,
        'step_id' => $step->id,
    ]);
    ExecutionToolCall::factory()->withName('search')->create([
        'execution_id' => $execution->id,
        'step_id' => $step->id,
    ]);
    ExecutionToolCall::factory()->withName('calculate')->create([
        'execution_id' => $execution->id,
        'step_id' => $step->id,
    ]);

    expect(ExecutionToolCall::forTool('search')->count())->toBe(2)
        ->and(ExecutionToolCall::forTool('calculate')->count())->toBe(1);
});

it('execution relationship returns parent execution', function () {
    $execution = Execution::factory()->create();
    $step = ExecutionStep::factory()->create(['execution_id' => $execution->id]);
    $toolCall = ExecutionToolCall::factory()->create([
        'execution_id' => $execution->id,
        'step_id' => $step->id,
    ]);

    expect($toolCall->execution)->toBeInstanceOf(Execution::class)
        ->and($toolCall->execution->id)->toBe($execution->id);
});

it('step relationship returns parent step', function () {
    $execution = Execution::factory()->create();
    $step = ExecutionStep::factory()->create(['execution_id' => $execution->id]);
    $toolCall = ExecutionToolCall::factory()->create([
        'execution_id' => $execution->id,
        'step_id' => $step->id,
    ]);

    expect($toolCall->step)->toBeInstanceOf(ExecutionStep::class)
        ->and($toolCall->step->id)->toBe($step->id);
});

it('scopePending filters pending tool calls', function () {
    $execution = Execution::factory()->create();
    $step = ExecutionStep::factory()->create(['execution_id' => $execution->id]);

    ExecutionToolCall::factory()->create([
        'execution_id' => $execution->id,
        'step_id' => $step->id,
        'status' => ExecutionStatus::Pending,
    ]);
    ExecutionToolCall::factory()->completed()->create([
        'execution_id' => $execution->id,
        'step_id' => $step->id,
    ]);

    expect(ExecutionToolCall::pending()->count())->toBe(1);
});

it('scopeCompleted filters completed tool calls', function () {
    $execution = Execution::factory()->create();
    $step = ExecutionStep::factory()->create(['execution_id' => $execution->id]);

    ExecutionToolCall::factory()->completed()->create([
        'execution_id' => $execution->id,
        'step_id' => $step->id,
    ]);
    ExecutionToolCall::factory()->completed()->create([
        'execution_id' => $execution->id,
        'step_id' => $step->id,
    ]);
    ExecutionToolCall::factory()->create([
        'execution_id' => $execution->id,
        'step_id' => $step->id,
        'status' => ExecutionStatus::Pending,
    ]);

    expect(ExecutionToolCall::completed()->count())->toBe(2);
});

it('scopeProcessing filters processing tool calls', function () {
    $execution = Execution::factory()->create();
    $step = ExecutionStep::factory()->create(['execution_id' => $execution->id]);

    ExecutionToolCall::factory()->create([
        'execution_id' => $execution->id,
        'step_id' => $step->id,
        'status' => ExecutionStatus::Processing,
    ]);
    ExecutionToolCall::factory()->completed()->create([
        'execution_id' => $execution->id,
        'step_id' => $step->id,
    ]);

    expect(ExecutionToolCall::processing()->count())->toBe(1);
});
