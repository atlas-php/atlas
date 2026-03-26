<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Queue\Jobs;

use Atlasphp\Atlas\AtlasConfig;
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
 * @property-read array<string, mixed> $payload
 */
trait TracksExecution
{
    /**
     * Transition execution to processing state.
     *
     * Called when the worker picks up the job. Allows re-entry on retries
     * (status may already be Processing from a previous attempt) but skips
     * terminal states to prevent overwriting completed/failed records.
     */
    protected function transitionToProcessing(): void
    {
        $execution = $this->resolveTrackedExecution();

        if ($execution === null) {
            return;
        }

        // Skip terminal states — allow re-entry on retries (Processing from previous attempt).
        if (in_array($execution->status, [ExecutionStatus::Completed, ExecutionStatus::Failed], true)) {
            return;
        }

        // On retry, started_at is intentionally updated to the retry time so duration_ms
        // reflects the actual successful attempt duration, not total time since first dispatch.
        $execution->update([
            'status' => ExecutionStatus::Processing,
            'started_at' => now(),
        ]);

        $identity = $this->payloadIdentity();

        event(new ExecutionProcessing(
            executionId: $this->executionId,
            channel: $this->broadcastChannel,
            provider: $identity['provider'],
            model: $identity['model'],
            agentKey: $identity['agentKey'],
        ));
    }

    /**
     * Mark execution as completed. Defense-in-depth for non-agent queued requests
     * where TrackProviderCall handles the primary completion path.
     *
     * Guards against double-completion: only transitions from Processing status.
     * For agent requests, TrackExecution middleware completes first, so this is a no-op.
     */
    protected function markExecutionCompleted(): void
    {
        $execution = $this->resolveTrackedExecution();

        if ($execution === null || $execution->status !== ExecutionStatus::Processing) {
            return;
        }

        $durationMs = $execution->started_at !== null
            ? (int) abs(now()->diffInMilliseconds($execution->started_at))
            : null;

        $execution->markCompleted($durationMs);
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
     * Extract common identity fields from the queue payload.
     *
     * @return array{provider: ?string, model: ?string, agentKey: ?string}
     */
    protected function payloadIdentity(): array
    {
        return [
            'provider' => $this->payload['provider'] ?? null,
            'model' => $this->payload['model'] ?? null,
            'agentKey' => $this->payload['key'] ?? null,
        ];
    }

    /**
     * Resolve the tracked execution if persistence is enabled and the record exists.
     *
     * Direct config read: cannot use ExecutionService here — it is a scoped singleton
     * bound to the request lifecycle and must not be resolved in a queue worker context.
     */
    private function resolveTrackedExecution(): ?Execution
    {
        if ($this->executionId === null) {
            return null;
        }

        if (! app(AtlasConfig::class)->persistenceEnabled) {
            return null;
        }

        /** @var class-string<Execution> $executionModel */
        $executionModel = app(AtlasConfig::class)->model('execution', Execution::class);

        return $executionModel::find($this->executionId);
    }
}
