<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\ConversationMessage;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\ExecutionStep;
use Atlasphp\Atlas\Persistence\Models\ExecutionToolCall;
use Atlasphp\Atlas\Responses\Usage;

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

it('markCompleted sets status, completed_at, duration, and usage', function () {
    $execution = Execution::factory()->create();

    $usage = new Usage(inputTokens: 300, outputTokens: 125);
    $execution->markCompleted(3000, $usage);

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Completed)
        ->and($execution->completed_at)->not->toBeNull()
        ->and($execution->duration_ms)->toBe(3000)
        ->and($execution->usage)->toBe(['inputTokens' => 300, 'outputTokens' => 125]);
});

it('markFailed sets status, error, completed_at, and usage', function () {
    $execution = Execution::factory()->create();

    $usage = new Usage(inputTokens: 50, outputTokens: 10);
    $execution->markFailed('Provider timeout', 2000, $usage);

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Failed)
        ->and($execution->error)->toBe('Provider timeout')
        ->and($execution->completed_at)->not->toBeNull()
        ->and($execution->duration_ms)->toBe(2000)
        ->and($execution->usage)->toBe(['inputTokens' => 50, 'outputTokens' => 10]);
});

it('scopeOfType filters correctly', function () {
    Execution::factory()->ofType(ExecutionType::Text)->create();
    Execution::factory()->ofType(ExecutionType::Text)->create();
    Execution::factory()->ofType(ExecutionType::Image)->create();

    expect(Execution::ofType(ExecutionType::Text)->count())->toBe(2)
        ->and(Execution::ofType(ExecutionType::Image)->count())->toBe(1);
});

it('scopeProducedAssets filters executions that have assets', function () {
    $withAsset = Execution::factory()->create();
    Asset::factory()->create(['execution_id' => $withAsset->id]);
    Execution::factory()->create(); // no assets

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

it('getTotalTokensAttribute returns sum of input and output from usage JSON', function () {
    $execution = Execution::factory()->create([
        'usage' => ['inputTokens' => 150, 'outputTokens' => 75],
    ]);

    expect($execution->total_tokens)->toBe(225);
});

it('steps relationship returns ordered by sequence', function () {
    $execution = Execution::factory()->create();

    ExecutionStep::factory()->create(['execution_id' => $execution->id, 'sequence' => 3]);
    ExecutionStep::factory()->create(['execution_id' => $execution->id, 'sequence' => 1]);
    ExecutionStep::factory()->create(['execution_id' => $execution->id, 'sequence' => 2]);

    expect($execution->steps->pluck('sequence')->toArray())->toBe([1, 2, 3]);
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

it('assets relationship returns related assets', function () {
    $execution = Execution::factory()->create();
    Asset::factory()->create(['execution_id' => $execution->id]);
    Asset::factory()->create(['execution_id' => $execution->id]);

    expect($execution->assets)->toHaveCount(2);
});

it('message relationship returns the linked message', function () {
    $conversation = Conversation::factory()->create();
    $execution = Execution::factory()->create([
        'conversation_id' => $conversation->id,
    ]);
    $message = ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'execution_id' => $execution->id,
    ]);

    expect($execution->message)->toBeInstanceOf(ConversationMessage::class)
        ->and($execution->message->id)->toBe($message->id);
});
