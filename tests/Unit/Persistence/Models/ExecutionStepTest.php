<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\ExecutionStep;
use Atlasphp\Atlas\Persistence\Models\ExecutionToolCall;

it('creates in pending status via factory', function () {
    $step = ExecutionStep::factory()->create();

    expect($step->exists)->toBeTrue()
        ->and($step->status)->toBe(ExecutionStatus::Pending);
});

it('recordResponse fills content, reasoning, tokens, and finish_reason', function () {
    $step = ExecutionStep::factory()->create();

    $step->recordResponse(
        content: 'The answer is 42',
        reasoning: 'I computed this carefully',
        inputTokens: 100,
        outputTokens: 50,
        finishReason: 'stop',
    );

    $step->refresh();

    expect($step->content)->toBe('The answer is 42')
        ->and($step->reasoning)->toBe('I computed this carefully')
        ->and($step->input_tokens)->toBe(100)
        ->and($step->output_tokens)->toBe(50)
        ->and($step->finish_reason)->toBe('stop');
});

it('markCompleted sets status and duration', function () {
    $step = ExecutionStep::factory()->create();

    $step->markCompleted(1500);

    $step->refresh();

    expect($step->status)->toBe(ExecutionStatus::Completed)
        ->and($step->completed_at)->not->toBeNull()
        ->and($step->duration_ms)->toBe(1500);
});

it('markFailed sets status and error', function () {
    $step = ExecutionStep::factory()->create();

    $step->markFailed('Connection refused', 500);

    $step->refresh();

    expect($step->status)->toBe(ExecutionStatus::Failed)
        ->and($step->error)->toBe('Connection refused')
        ->and($step->completed_at)->not->toBeNull()
        ->and($step->duration_ms)->toBe(500);
});

it('hasToolCalls returns true when finish_reason is tool_calls', function () {
    $withTools = ExecutionStep::factory()->withToolCalls()->create();
    $withoutTools = ExecutionStep::factory()->completed()->create();

    expect($withTools->hasToolCalls())->toBeTrue()
        ->and($withoutTools->hasToolCalls())->toBeFalse();
});

it('getTotalTokensAttribute returns sum of input and output tokens', function () {
    $step = ExecutionStep::factory()->create([
        'input_tokens' => 80,
        'output_tokens' => 40,
    ]);

    expect($step->total_tokens)->toBe(120);
});

it('toolCalls relationship returns related tool calls', function () {
    $execution = Execution::factory()->create();
    $step = ExecutionStep::factory()->create(['execution_id' => $execution->id]);

    ExecutionToolCall::factory()->count(3)->create([
        'execution_id' => $execution->id,
        'step_id' => $step->id,
    ]);

    // Unrelated tool call on a different step
    $otherStep = ExecutionStep::factory()->create(['execution_id' => $execution->id]);
    ExecutionToolCall::factory()->create([
        'execution_id' => $execution->id,
        'step_id' => $otherStep->id,
    ]);

    expect($step->toolCalls)->toHaveCount(3);
});
