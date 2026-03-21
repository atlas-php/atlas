<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\ExecutionStep;
use Atlasphp\Atlas\Persistence\Models\ExecutionToolCall;

it('creates in pending status via factory', function () {
    $execution = Execution::factory()->create();

    expect($execution->exists)->toBeTrue()
        ->and($execution->status)->toBe(ExecutionStatus::Pending);
});

it('markQueued transitions to queued', function () {
    $execution = Execution::factory()->create();

    $execution->markQueued();

    expect($execution->fresh()->status)->toBe(ExecutionStatus::Queued);
});

it('markCompleted sets status, completed_at, and aggregates tokens from steps', function () {
    $execution = Execution::factory()->create();

    ExecutionStep::factory()->create([
        'execution_id' => $execution->id,
        'input_tokens' => 100,
        'output_tokens' => 50,
    ]);

    ExecutionStep::factory()->create([
        'execution_id' => $execution->id,
        'input_tokens' => 200,
        'output_tokens' => 75,
    ]);

    $execution->markCompleted(3000);

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Completed)
        ->and($execution->completed_at)->not->toBeNull()
        ->and($execution->duration_ms)->toBe(3000)
        ->and($execution->total_input_tokens)->toBe(300)
        ->and($execution->total_output_tokens)->toBe(125);
});

it('markFailed sets status, error, and completed_at', function () {
    $execution = Execution::factory()->create();

    ExecutionStep::factory()->create([
        'execution_id' => $execution->id,
        'input_tokens' => 50,
        'output_tokens' => 10,
    ]);

    $execution->markFailed('Provider timeout', 2000);

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Failed)
        ->and($execution->error)->toBe('Provider timeout')
        ->and($execution->completed_at)->not->toBeNull()
        ->and($execution->duration_ms)->toBe(2000)
        ->and($execution->total_input_tokens)->toBe(50)
        ->and($execution->total_output_tokens)->toBe(10);
});

it('scopeOfType filters correctly', function () {
    Execution::factory()->ofType(ExecutionType::Text)->create();
    Execution::factory()->ofType(ExecutionType::Text)->create();
    Execution::factory()->ofType(ExecutionType::Image)->create();

    expect(Execution::ofType(ExecutionType::Text)->count())->toBe(2)
        ->and(Execution::ofType(ExecutionType::Image)->count())->toBe(1);
});

it('scopeProducedAssets filters where asset_id not null', function () {
    $asset = Asset::factory()->create();

    Execution::factory()->create(['asset_id' => $asset->id]);
    Execution::factory()->create(['asset_id' => null]);

    expect(Execution::producedAssets()->count())->toBe(1);
});

it('status scopes filter correctly', function () {
    Execution::factory()->create(['status' => ExecutionStatus::Pending]);
    Execution::factory()->queued()->create();
    Execution::factory()->processing()->create();
    Execution::factory()->completed()->create();
    Execution::factory()->failed()->create();

    expect(Execution::pending()->count())->toBe(1)
        ->and(Execution::queued()->count())->toBe(1)
        ->and(Execution::processing()->count())->toBe(1)
        ->and(Execution::completed()->count())->toBe(1)
        ->and(Execution::failed()->count())->toBe(1);
});

it('scopeForAgent filters by agent', function () {
    Execution::factory()->withAgent('writer')->create();
    Execution::factory()->withAgent('writer')->create();
    Execution::factory()->withAgent('coder')->create();

    expect(Execution::forAgent('writer')->count())->toBe(2)
        ->and(Execution::forAgent('coder')->count())->toBe(1);
});

it('scopeForProvider filters by provider', function () {
    Execution::factory()->create(['provider' => 'openai']);
    Execution::factory()->create(['provider' => 'anthropic']);
    Execution::factory()->create(['provider' => 'openai']);

    expect(Execution::forProvider('openai')->count())->toBe(2)
        ->and(Execution::forProvider('anthropic')->count())->toBe(1);
});

it('getTotalTokensAttribute returns sum of input and output', function () {
    $execution = Execution::factory()->create([
        'total_input_tokens' => 150,
        'total_output_tokens' => 75,
    ]);

    expect($execution->total_tokens)->toBe(225);
});

it('steps relationship returns ordered by sequence', function () {
    $execution = Execution::factory()->create();

    ExecutionStep::factory()->create(['execution_id' => $execution->id, 'sequence' => 2]);
    ExecutionStep::factory()->create(['execution_id' => $execution->id, 'sequence' => 0]);
    ExecutionStep::factory()->create(['execution_id' => $execution->id, 'sequence' => 1]);

    expect($execution->steps->pluck('sequence')->toArray())->toBe([0, 1, 2]);
});

it('toolCalls relationship returns related tool calls', function () {
    $execution = Execution::factory()->create();
    $step = ExecutionStep::factory()->create(['execution_id' => $execution->id]);

    ExecutionToolCall::factory()->count(2)->create([
        'execution_id' => $execution->id,
        'step_id' => $step->id,
    ]);

    expect($execution->toolCalls)->toHaveCount(2);
});

it('asset relationship returns related asset', function () {
    $asset = Asset::factory()->create();
    $execution = Execution::factory()->create(['asset_id' => $asset->id]);

    expect($execution->asset)->toBeInstanceOf(Asset::class)
        ->and($execution->asset->id)->toBe($asset->id);
});
