<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Queue\Jobs\TracksExecution;
use Illuminate\Broadcasting\Channel;

function createTracksExecutionHarness(?int $executionId): object
{
    return new class($executionId)
    {
        use TracksExecution;

        public readonly ?Channel $broadcastChannel;

        public function __construct(public readonly ?int $executionId)
        {
            $this->broadcastChannel = null;
        }

        // Expose protected methods for testing
        public function callTransitionToProcessing(): void
        {
            $this->transitionToProcessing();
        }

        public function callMarkExecutionFailed(Throwable $exception): void
        {
            $this->markExecutionFailed($exception);
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

it('transitionToProcessing no-ops when executionId is null', function () {
    $harness = createTracksExecutionHarness(null);

    // Should not throw
    $harness->callTransitionToProcessing();

    expect(true)->toBeTrue();
});

it('transitionToProcessing no-ops when persistence disabled', function () {
    $execution = Execution::factory()->queued()->create();

    config()->set('atlas.persistence.enabled', false);

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

it('transitionToProcessing only transitions from queued status', function () {
    $execution = Execution::factory()->create(['status' => ExecutionStatus::Pending]);

    $harness = createTracksExecutionHarness($execution->id);
    $harness->callTransitionToProcessing();

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Pending);
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

    $harness = createTracksExecutionHarness($execution->id);
    $harness->callMarkExecutionFailed(new RuntimeException('fail'));

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::Processing);
});
