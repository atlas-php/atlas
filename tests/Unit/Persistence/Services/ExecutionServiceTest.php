<?php

declare(strict_types=1);

use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Enums\ToolCallType;
use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\ConversationMessage;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\ExecutionStep;
use Atlasphp\Atlas\Persistence\Models\ExecutionToolCall;
use Atlasphp\Atlas\Persistence\Services\ExecutionService;
use Atlasphp\Atlas\Responses\Usage;

beforeEach(function () {
    $this->service = app(ExecutionService::class);
});

// ─── createExecution ───────────────────────────────────────────────

it('creates execution in pending status with correct provider and model', function () {
    $execution = $this->service->createExecution(
        provider: 'anthropic',
        model: 'claude-4',
    );

    expect($execution)->toBeInstanceOf(Execution::class)
        ->and($execution->exists)->toBeTrue()
        ->and($execution->status)->toBe(ExecutionStatus::Pending)
        ->and($execution->provider)->toBe('anthropic')
        ->and($execution->model)->toBe('claude-4')
        ->and($execution->type)->toBe(ExecutionType::Text)
        ->and($execution->usage)->toBeNull();
});

it('creates execution with optional parameters', function () {
    $conversation = Conversation::factory()->create();
    $message = ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
    ]);

    $execution = $this->service->createExecution(
        provider: 'openai',
        model: 'gpt-5',
        meta: ['key' => 'value'],
        agent: 'writer-agent',
        conversationId: $conversation->id,
        messageId: $message->id,
        type: ExecutionType::Image,
    );

    $message->refresh();

    expect($execution->agent)->toBe('writer-agent')
        ->and($execution->conversation_id)->toBe($conversation->id)
        ->and($message->execution_id)->toBe($execution->id)
        ->and($execution->type)->toBe(ExecutionType::Image)
        ->and($execution->metadata)->toBe(['key' => 'value']);
});

// ─── markQueued ────────────────────────────────────────────────────

it('transitions pending to queued', function () {
    $this->service->createExecution(provider: 'openai', model: 'gpt-5');

    $this->service->markQueued();

    $execution = $this->service->getExecution();
    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Queued);
});

it('markQueued no-ops without active execution', function () {
    $this->service->markQueued();

    expect($this->service->getExecution())->toBeNull();
});

// ─── beginExecution ────────────────────────────────────────────────

it('transitions to processing and sets started_at', function () {
    $this->service->createExecution(provider: 'openai', model: 'gpt-5');

    $this->service->beginExecution();

    $execution = $this->service->getExecution();
    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Processing)
        ->and($execution->started_at)->not->toBeNull();
});

// ─── completeExecution ─────────────────────────────────────────────

it('transitions to completed and records usage', function () {
    $this->service->createExecution(provider: 'openai', model: 'gpt-5');
    $this->service->beginExecution();

    $usage = new Usage(inputTokens: 180, outputTokens: 90);
    $this->service->completeExecution($usage);

    $execution = $this->service->getExecution();
    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Completed)
        ->and($execution->duration_ms)->toBeGreaterThanOrEqual(0)
        ->and($execution->completed_at)->not->toBeNull()
        ->and($execution->usage)->toBe(['input_tokens' => 180, 'output_tokens' => 90]);
});

// ─── failExecution ─────────────────────────────────────────────────

it('transitions to failed, records error, marks in-flight step as failed', function () {
    $this->service->createExecution(provider: 'openai', model: 'gpt-5');
    $this->service->beginExecution();

    $step = $this->service->createStep();
    $this->service->beginStep();

    $exception = new RuntimeException('Provider timeout');

    $this->service->failExecution($exception);

    $execution = $this->service->getExecution();
    $execution->refresh();
    $step->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Failed)
        ->and($execution->error)->toContain('Provider timeout')
        ->and($execution->error)->toContain('RuntimeException')
        ->and($step->status)->toBe(ExecutionStatus::Failed)
        ->and($step->error)->toBe('Provider timeout');
});

it('failExecution no-ops without active execution', function () {
    $this->service->failExecution(new RuntimeException('test'));

    expect($this->service->getExecution())->toBeNull();
});

// ─── createStep ────────────────────────────────────────────────────

it('creates step in pending with correct sequence', function () {
    $this->service->createExecution(provider: 'openai', model: 'gpt-5');

    $step1 = $this->service->createStep();
    $step2 = $this->service->createStep();

    expect($step1)->toBeInstanceOf(ExecutionStep::class)
        ->and($step1->status)->toBe(ExecutionStatus::Pending)
        ->and($step1->sequence)->toBe(1)
        ->and($step2->sequence)->toBe(2);
});

it('throws when creating step without active execution', function () {
    $this->service->createStep();
})->throws(RuntimeException::class, 'Cannot create a step without an active execution.');

// ─── beginStep ─────────────────────────────────────────────────────

it('transitions step to processing', function () {
    $this->service->createExecution(provider: 'openai', model: 'gpt-5');
    $step = $this->service->createStep();

    $this->service->beginStep();

    $step->refresh();

    expect($step->status)->toBe(ExecutionStatus::Processing)
        ->and($step->started_at)->not->toBeNull();
});

// ─── completeStep ──────────────────────────────────────────────────

it('transitions step to completed and retains reference for tool calls', function () {
    $this->service->createExecution(provider: 'openai', model: 'gpt-5');
    $step = $this->service->createStep();
    $this->service->beginStep();

    $this->service->completeStep();

    $step->refresh();

    // Step is completed but reference retained for tool call linkage
    expect($step->status)->toBe(ExecutionStatus::Completed)
        ->and($step->completed_at)->not->toBeNull()
        ->and($this->service->currentStep())->not->toBeNull()
        ->and($this->service->currentStep()->id)->toBe($step->id);
});

// ─── createToolCall ────────────────────────────────────────────────

it('creates tool call in pending with arguments', function () {
    $this->service->createExecution(provider: 'openai', model: 'gpt-5');
    $this->service->createStep();

    $toolCall = new ToolCall(
        id: 'call_abc123',
        name: 'search',
        arguments: ['query' => 'test'],
    );

    $record = $this->service->createToolCall($toolCall);

    expect($record)->toBeInstanceOf(ExecutionToolCall::class)
        ->and($record->status)->toBe(ExecutionStatus::Pending)
        ->and($record->tool_call_id)->toBe('call_abc123')
        ->and($record->name)->toBe('search')
        ->and($record->type)->toBe(ToolCallType::Local)
        ->and($record->arguments)->toBe(['query' => 'test']);
});

it('throws when creating tool call without active execution and step', function () {
    $toolCall = new ToolCall(id: 'call_xyz', name: 'test', arguments: []);

    $this->service->createToolCall($toolCall);
})->throws(RuntimeException::class, 'Cannot track a tool call without an active execution and step.');

// ─── beginToolCall ─────────────────────────────────────────────────

it('transitions tool call to processing and returns start time', function () {
    $this->service->createExecution(provider: 'openai', model: 'gpt-5');
    $this->service->createStep();

    $toolCall = new ToolCall(id: 'call_abc', name: 'search', arguments: []);
    $record = $this->service->createToolCall($toolCall);

    $startTime = $this->service->beginToolCall($record);

    $record->refresh();

    expect($startTime)->toBeFloat()
        ->and($record->status)->toBe(ExecutionStatus::Processing)
        ->and($record->started_at)->not->toBeNull();
});

// ─── completeToolCall ──────────────────────────────────────────────

it('sets result and duration and clears currentToolCall', function () {
    $this->service->createExecution(provider: 'openai', model: 'gpt-5');
    $this->service->createStep();

    $toolCall = new ToolCall(id: 'call_abc', name: 'search', arguments: []);
    $record = $this->service->createToolCall($toolCall);
    $startTime = $this->service->beginToolCall($record);

    $this->service->completeToolCall($record, $startTime, 'Found 5 results');

    $record->refresh();

    expect($record->status)->toBe(ExecutionStatus::Completed)
        ->and($record->result)->toBe('Found 5 results')
        ->and($record->duration_ms)->toBeGreaterThanOrEqual(0)
        ->and($this->service->getCurrentToolCall())->toBeNull();
});

// ─── failToolCall ──────────────────────────────────────────────────

it('sets error on tool call failure', function () {
    $this->service->createExecution(provider: 'openai', model: 'gpt-5');
    $this->service->createStep();

    $toolCall = new ToolCall(id: 'call_abc', name: 'search', arguments: []);
    $record = $this->service->createToolCall($toolCall);
    $startTime = $this->service->beginToolCall($record);

    $this->service->failToolCall($record, $startTime, 'Connection refused');

    $record->refresh();

    expect($record->status)->toBe(ExecutionStatus::Failed)
        ->and($record->result)->toBe('Connection refused')
        ->and($record->duration_ms)->toBeGreaterThanOrEqual(0);
});

// ─── completeDirectExecution ───────────────────────────────────────

it('records usage directly without step aggregation', function () {
    $this->service->createExecution(provider: 'openai', model: 'gpt-5');
    $this->service->beginExecution();

    $this->service->completeDirectExecution(new Usage(inputTokens: 200, outputTokens: 100));

    $execution = $this->service->getExecution();
    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Completed)
        ->and($execution->usage)->toBe(['input_tokens' => 200, 'output_tokens' => 100])
        ->and($execution->completed_at)->not->toBeNull()
        ->and($execution->duration_ms)->toBeGreaterThanOrEqual(0);
});

// ─── linkAsset ─────────────────────────────────────────────────────

it('tracks last asset via linkAsset', function () {
    $this->service->createExecution(provider: 'openai', model: 'gpt-5');

    $asset = Asset::factory()->create();
    $this->service->linkAsset($asset);

    expect($this->service->getLastAsset())->toBe($asset);
});

it('linkAsset sets null without asset', function () {
    $this->service->createExecution(provider: 'openai', model: 'gpt-5');
    $this->service->linkAsset(null);

    expect($this->service->getLastAsset())->toBeNull();
});

// ─── hasActiveExecution ────────────────────────────────────────────

it('returns true when execution exists in service', function () {
    $this->service->createExecution(provider: 'openai', model: 'gpt-5');

    expect($this->service->hasActiveExecution())->toBeTrue();
});

it('returns false when no execution exists in service', function () {
    expect($this->service->hasActiveExecution())->toBeFalse();
});

// ─── getExecution / getCurrentToolCall ─────────────────────────────

it('getExecution returns current execution', function () {
    $execution = $this->service->createExecution(provider: 'openai', model: 'gpt-5');

    expect($this->service->getExecution()->id)->toBe($execution->id);
});

it('getCurrentToolCall returns null when no tool call active', function () {
    expect($this->service->getCurrentToolCall())->toBeNull();
});

it('getCurrentToolCall returns active tool call', function () {
    $this->service->createExecution(provider: 'openai', model: 'gpt-5');
    $this->service->createStep();

    $toolCall = new ToolCall(id: 'call_abc', name: 'search', arguments: []);
    $this->service->createToolCall($toolCall);

    expect($this->service->getCurrentToolCall())->not->toBeNull()
        ->and($this->service->getCurrentToolCall()->name)->toBe('search');
});

// ─── reset ─────────────────────────────────────────────────────────

it('clears all state on reset', function () {
    $this->service->createExecution(provider: 'openai', model: 'gpt-5');
    $this->service->createStep();

    $toolCall = new ToolCall(id: 'call_abc', name: 'search', arguments: []);
    $this->service->createToolCall($toolCall);

    $this->service->reset();

    expect($this->service->getExecution())->toBeNull()
        ->and($this->service->currentStep())->toBeNull()
        ->and($this->service->getCurrentToolCall())->toBeNull()
        ->and($this->service->hasActiveExecution())->toBeFalse();
});

// ─── Null duration_ms paths ───────────────────────────────────────

it('completeExecution returns null duration when beginExecution was never called', function () {
    $this->service->createExecution(provider: 'openai', model: 'gpt-5');

    // Skip beginExecution — executionStartTime stays 0
    $this->service->completeExecution();

    $execution = Execution::first();
    expect($execution->status)->toBe(ExecutionStatus::Completed)
        ->and($execution->duration_ms)->toBeNull();
});

it('failExecution returns null duration when beginExecution was never called', function () {
    $this->service->createExecution(provider: 'openai', model: 'gpt-5');

    // Skip beginExecution
    $this->service->failExecution(new RuntimeException('test'));

    $execution = Execution::first();
    expect($execution->status)->toBe(ExecutionStatus::Failed)
        ->and($execution->duration_ms)->toBeNull();
});

it('completeStep returns null duration when beginStep was never called', function () {
    $this->service->createExecution(provider: 'openai', model: 'gpt-5');
    $this->service->beginExecution();
    $this->service->createStep();

    // Skip beginStep — stepStartTime stays 0
    $this->service->completeStep();

    $step = ExecutionStep::first();
    expect($step->status)->toBe(ExecutionStatus::Completed)
        ->and($step->duration_ms)->toBeNull();
});

it('completeDirectExecution returns null duration when beginExecution was never called', function () {
    $this->service->createExecution(provider: 'openai', model: 'gpt-5');

    // Skip beginExecution
    $this->service->completeDirectExecution(new Usage(inputTokens: 50, outputTokens: 25));

    $execution = Execution::first();
    expect($execution->status)->toBe(ExecutionStatus::Completed)
        ->and($execution->usage)->toBe(['input_tokens' => 50, 'output_tokens' => 25])
        ->and($execution->duration_ms)->toBeNull();
});

// ─── completeVoiceExecution ────────────────────────────────────────

it('completes a processing voice execution', function () {
    $execution = Execution::factory()->create([
        'status' => ExecutionStatus::Processing,
        'started_at' => now()->subSeconds(5),
    ]);

    $result = $this->service->completeVoiceExecution($execution->id);

    expect($result)->not->toBeNull()
        ->and($result->status)->toBe(ExecutionStatus::Completed)
        ->and($result->completed_at)->not->toBeNull()
        ->and($result->duration_ms)->toBeGreaterThanOrEqual(0);
});

it('returns null for non-processing execution', function () {
    $execution = Execution::factory()->create([
        'status' => ExecutionStatus::Completed,
    ]);

    $result = $this->service->completeVoiceExecution($execution->id);

    expect($result)->toBeNull();
});

it('returns null for non-existent execution', function () {
    $result = $this->service->completeVoiceExecution(99999);

    expect($result)->toBeNull();
});

it('merges extra metadata when completing voice execution', function () {
    $execution = Execution::factory()->create([
        'status' => ExecutionStatus::Processing,
        'started_at' => now(),
        'metadata' => ['existing' => 'data'],
    ]);

    $result = $this->service->completeVoiceExecution($execution->id, ['voice' => 'info']);

    expect($result->metadata)->toBe(['existing' => 'data', 'voice' => 'info'])
        ->and($result->status)->toBe(ExecutionStatus::Completed);
});

it('sets metadata when extra meta provided and no existing metadata', function () {
    $execution = Execution::factory()->create([
        'status' => ExecutionStatus::Processing,
        'started_at' => now(),
        'metadata' => null,
    ]);

    $result = $this->service->completeVoiceExecution($execution->id, ['cleanup' => true]);

    expect($result->metadata)->toBe(['cleanup' => true]);
});

it('completes without metadata when extra meta is null', function () {
    $execution = Execution::factory()->create([
        'status' => ExecutionStatus::Processing,
        'started_at' => now(),
        'metadata' => ['keep' => 'this'],
    ]);

    $result = $this->service->completeVoiceExecution($execution->id);

    expect($result->metadata)->toBe(['keep' => 'this'])
        ->and($result->status)->toBe(ExecutionStatus::Completed);
});
