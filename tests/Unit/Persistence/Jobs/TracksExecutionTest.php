<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Events\ExecutionProcessing;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Queue\Jobs\TracksExecution;
use Illuminate\Broadcasting\Channel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

function createTracksExecutionHarness(?int $executionId, array $payload = []): object
{
    return new class($executionId, $payload)
    {
        use TracksExecution;

        public readonly ?Channel $broadcastChannel;

        /**
         * @param  array<string, mixed>  $payload
         */
        public function __construct(
            public readonly ?int $executionId,
            public readonly array $payload = [],
        ) {
            $this->broadcastChannel = null;
        }

        // Expose protected methods for testing
        public function callTransitionToProcessing(): void
        {
            $this->transitionToProcessing();
        }

        public function callMarkExecutionCompleted(): void
        {
            $this->markExecutionCompleted();
        }

        public function callMarkExecutionFailed(Throwable $exception): void
        {
            $this->markExecutionFailed($exception);
        }

        /** @return array{provider: ?string, model: ?string, agentKey: ?string} */
        public function callPayloadIdentity(): array
        {
            return $this->payloadIdentity();
        }
    };
}

// ─── transitionToProcessing ─────────────────────────────────────────────────

it('transitionToProcessing transitions queued execution to processing', function () {
    $execution = Execution::factory()->queued()->create();

    $harness = createTracksExecutionHarness($execution->id);
    $harness->callTransitionToProcessing();

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Processing)
        ->and($execution->started_at)->not->toBeNull();
});

it('transitionToProcessing allows re-entry on retry when already processing', function () {
    Event::fake([ExecutionProcessing::class]);

    $originalTime = Carbon::create(2025, 1, 1, 12, 0, 0);
    $retryTime = Carbon::create(2025, 1, 1, 12, 5, 0);

    $execution = Execution::factory()->processing()->create([
        'started_at' => $originalTime,
    ]);

    Carbon::setTestNow($retryTime);

    $harness = createTracksExecutionHarness($execution->id);
    $harness->callTransitionToProcessing();

    Carbon::setTestNow(); // Reset

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Processing)
        ->and($execution->started_at->equalTo($retryTime))->toBeTrue();

    Event::assertDispatched(ExecutionProcessing::class);
});

it('transitionToProcessing transitions pending to processing', function () {
    $execution = Execution::factory()->create(['status' => ExecutionStatus::Pending]);

    $harness = createTracksExecutionHarness($execution->id);
    $harness->callTransitionToProcessing();

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Processing);
});

it('transitionToProcessing skips completed execution', function () {
    $execution = Execution::factory()->create(['status' => ExecutionStatus::Completed]);

    $harness = createTracksExecutionHarness($execution->id);
    $harness->callTransitionToProcessing();

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Completed);
});

it('transitionToProcessing skips failed execution', function () {
    $execution = Execution::factory()->create(['status' => ExecutionStatus::Failed]);

    $harness = createTracksExecutionHarness($execution->id);
    $harness->callTransitionToProcessing();

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Failed);
});

it('transitionToProcessing no-ops when executionId is null', function () {
    $harness = createTracksExecutionHarness(null);

    // Should not throw
    $harness->callTransitionToProcessing();

    expect(true)->toBeTrue();
});

it('transitionToProcessing no-ops when persistence disabled', function () {
    $execution = Execution::factory()->queued()->create();

    config()->set('atlas.persistence.enabled', false);
    AtlasConfig::refresh();

    $harness = createTracksExecutionHarness($execution->id);
    $harness->callTransitionToProcessing();

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Queued);
});

it('transitionToProcessing no-ops when execution not found', function () {
    $harness = createTracksExecutionHarness(99999);

    // Should not throw
    $harness->callTransitionToProcessing();

    expect(true)->toBeTrue();
});

// ─── markExecutionCompleted ─────────────────────────────────────────────────

it('markExecutionCompleted transitions processing to completed', function () {
    $execution = Execution::factory()->processing()->create([
        'started_at' => now()->subSeconds(10),
    ]);

    $harness = createTracksExecutionHarness($execution->id);
    $harness->callMarkExecutionCompleted();

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Completed)
        ->and($execution->completed_at)->not->toBeNull()
        ->and($execution->duration_ms)->toBeGreaterThan(0);
});

it('markExecutionCompleted is no-op when already completed', function () {
    $execution = Execution::factory()->create([
        'status' => ExecutionStatus::Completed,
        'completed_at' => now()->subMinutes(1),
    ]);

    $originalCompletedAt = $execution->completed_at->toISOString();

    $harness = createTracksExecutionHarness($execution->id);
    $harness->callMarkExecutionCompleted();

    $execution->refresh();

    // completed_at should not have changed
    expect($execution->status)->toBe(ExecutionStatus::Completed)
        ->and($execution->completed_at->toISOString())->toBe($originalCompletedAt);
});

it('markExecutionCompleted is no-op when failed', function () {
    $execution = Execution::factory()->create([
        'status' => ExecutionStatus::Failed,
        'error' => 'test error',
    ]);

    $harness = createTracksExecutionHarness($execution->id);
    $harness->callMarkExecutionCompleted();

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Failed);
});

it('markExecutionCompleted no-ops when executionId is null', function () {
    $harness = createTracksExecutionHarness(null);

    // Should not throw
    $harness->callMarkExecutionCompleted();

    expect(true)->toBeTrue();
});

it('markExecutionCompleted no-ops when persistence disabled', function () {
    $execution = Execution::factory()->processing()->create();

    config()->set('atlas.persistence.enabled', false);
    AtlasConfig::refresh();

    $harness = createTracksExecutionHarness($execution->id);
    $harness->callMarkExecutionCompleted();

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Processing);
});

// ─── markExecutionFailed ────────────────────────────────────────────────────

it('markExecutionFailed marks execution as failed', function () {
    $execution = Execution::factory()->processing()->create();

    $harness = createTracksExecutionHarness($execution->id);
    $harness->callMarkExecutionFailed(new RuntimeException('Provider timeout'));

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Failed)
        ->and($execution->error)->toBe('RuntimeException: Provider timeout')
        ->and($execution->completed_at)->not->toBeNull();
});

it('markExecutionFailed no-ops when executionId is null', function () {
    $harness = createTracksExecutionHarness(null);

    // Should not throw
    $harness->callMarkExecutionFailed(new RuntimeException('fail'));

    expect(true)->toBeTrue();
});

it('markExecutionFailed no-ops when persistence disabled', function () {
    $execution = Execution::factory()->processing()->create();

    config()->set('atlas.persistence.enabled', false);
    AtlasConfig::refresh();

    $harness = createTracksExecutionHarness($execution->id);
    $harness->callMarkExecutionFailed(new RuntimeException('fail'));

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Processing);
});

// ─── payloadIdentity ────────────────────────────────────────────────────────

it('payloadIdentity extracts correct values from payload', function () {
    $harness = createTracksExecutionHarness(1, [
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'key' => 'my-agent',
    ]);

    expect($harness->callPayloadIdentity())->toBe([
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'agentKey' => 'my-agent',
    ]);
});

it('payloadIdentity returns nulls for missing keys', function () {
    $harness = createTracksExecutionHarness(1, []);

    expect($harness->callPayloadIdentity())->toBe([
        'provider' => null,
        'model' => null,
        'agentKey' => null,
    ]);
});
