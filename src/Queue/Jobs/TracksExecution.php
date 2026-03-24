<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Queue\Jobs;

use Atlasphp\Atlas\Events\ExecutionProcessing;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Illuminate\Broadcasting\Channel;

/**
 * Shared persistence lifecycle for queued Atlas jobs.
 *
 * All DB operations are guarded behind persistence-enabled checks,
 * so queue works without persistence.
 *
 * @property-read int|null $executionId
 * @property-read Channel|null $broadcastChannel
 */
trait TracksExecution
{
    /**
     * Transition execution from queued → processing.
     * Called when the worker picks up the job.
     */
    protected function transitionToProcessing(): void
    {
        $execution = $this->resolveTrackedExecution();

        if ($execution === null || $execution->status !== ExecutionStatus::Queued) {
            return;
        }

        $execution->update([
            'status' => ExecutionStatus::Processing,
            'started_at' => now(),
        ]);

        event(new ExecutionProcessing(
            executionId: $this->executionId,
            channel: $this->broadcastChannel,
            provider: $this->payload['provider'] ?? null,
            model: $this->payload['model'] ?? null,
            agentKey: $this->payload['key'] ?? null,
        ));
    }

    /**
     * Mark execution as failed. Called from failed() when
     * all retries are exhausted.
     */
    protected function markExecutionFailed(\Throwable $exception): void
    {
        $execution = $this->resolveTrackedExecution();

        if ($execution === null) {
            return;
        }

        $execution->markFailed(
            get_class($exception).': '.$exception->getMessage(),
            null,
        );
    }

    /**
     * Resolve the tracked execution if persistence is enabled and the record exists.
     */
    private function resolveTrackedExecution(): ?Execution
    {
        if ($this->executionId === null) {
            return null;
        }

        if (! config('atlas.persistence.enabled', false)) {
            return null;
        }

        /** @var class-string<Execution> $executionModel */
        $executionModel = config('atlas.persistence.models.execution', Execution::class);

        return $executionModel::find($this->executionId);
    }
}
