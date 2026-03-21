<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Services;

use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Enums\ToolCallType;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\ExecutionStep;
use Atlasphp\Atlas\Persistence\Models\ExecutionToolCall;

/**
 * Class ExecutionService
 *
 * Stateful lifecycle tracker scoped to a single execution request. Holds references
 * to the current execution and step, tracks precise wall-clock timing via microtime,
 * and exposes create/begin/complete/fail methods that persistence middleware calls
 * as the agent progresses through its lifecycle.
 *
 * Records are created before things happen and updated after — if the agent crashes
 * at any point, every completed step is fully recorded, the in-flight step has
 * started_at but no completed_at, and the execution has status: processing.
 */
class ExecutionService
{
    protected ?Execution $execution = null;

    protected ?ExecutionStep $currentStep = null;

    protected ?ExecutionToolCall $currentToolCall = null;

    protected int $stepSequence = 0;

    /** @var float Precise start time for execution duration */
    protected float $executionStartTime = 0;

    /** @var float Precise start time for current step duration */
    protected float $stepStartTime = 0;

    // ─── Execution Lifecycle ────────────────────────────────────
    //
    // Every level follows the same pattern:
    //   create (pending) → begin (processing) → complete/fail
    //
    // Pending is the born state. If the system dies between create
    // and begin, you see a pending record that never started — you
    // know it never ran. Processing means actively working. Then
    // it resolves to completed or failed.
    //
    // Queued is execution-level only, for ->queue() async dispatch.
    // Steps and tools are synchronous — they skip queued entirely.

    /**
     * Create a new execution in pending state.
     * Called BEFORE anything runs. Record exists immediately.
     *
     * @param  array<string, mixed>  $meta
     */
    public function createExecution(
        string $provider,
        string $model,
        array $meta = [],
        ?string $agent = null,
        ?int $conversationId = null,
        ?int $messageId = null,
        ?ExecutionType $type = null,
    ): Execution {
        $executionModel = config('atlas.persistence.models.execution', Execution::class);

        $this->execution = $executionModel::create([
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'agent' => $agent,
            'type' => $type ?? ExecutionType::Text,
            'provider' => $provider,
            'model' => $model,
            'status' => ExecutionStatus::Pending,
            'total_input_tokens' => 0,
            'total_output_tokens' => 0,
            'metadata' => ! empty($meta) ? $meta : null,
        ]);

        $this->stepSequence = 0;

        return $this->execution;
    }

    /**
     * Transition pending → queued. Called when the execution is
     * dispatched to a queue instead of running inline.
     */
    public function markQueued(): void
    {
        if ($this->execution === null) {
            return;
        }

        $this->execution->markQueued();
    }

    /**
     * Transition pending → processing. Starts the wall-time timer.
     * Called when the executor actually begins work.
     */
    public function beginExecution(): void
    {
        if ($this->execution === null) {
            return;
        }

        $this->executionStartTime = microtime(true);

        $this->execution->update([
            'status' => ExecutionStatus::Processing,
            'started_at' => now(),
        ]);
    }

    /**
     * Transition processing → completed. Aggregates tokens, records duration.
     */
    public function completeExecution(): void
    {
        if ($this->execution === null) {
            return;
        }

        $durationMs = $this->executionStartTime > 0
            ? (int) ((microtime(true) - $this->executionStartTime) * 1000)
            : null;

        $this->execution->markCompleted($durationMs);
    }

    /**
     * Transition processing → failed. Records error, marks in-flight step failed too.
     */
    public function failExecution(\Throwable $exception): void
    {
        if ($this->execution === null) {
            return;
        }

        $durationMs = $this->executionStartTime > 0
            ? (int) ((microtime(true) - $this->executionStartTime) * 1000)
            : null;

        // Mark the in-flight step as failed too — you know exactly where it died
        if ($this->currentStep?->status === ExecutionStatus::Processing) {
            $stepDurationMs = $this->stepStartTime > 0
                ? (int) ((microtime(true) - $this->stepStartTime) * 1000)
                : null;
            $this->currentStep->markFailed($exception->getMessage(), $stepDurationMs);
        }

        $this->execution->markFailed(
            get_class($exception).': '.$exception->getMessage(),
            $durationMs,
        );
    }

    // ─── Step Lifecycle ─────────────────────────────────────────

    /**
     * Create a new step in pending state. Called BEFORE the provider call.
     */
    public function createStep(): ExecutionStep
    {
        if ($this->execution === null) {
            throw new \RuntimeException('Cannot create a step without an active execution.');
        }

        $stepModel = config('atlas.persistence.models.execution_step', ExecutionStep::class);

        $this->currentStep = $stepModel::create([
            'execution_id' => $this->execution->id,
            'sequence' => $this->stepSequence++,
            'status' => ExecutionStatus::Pending,
        ]);

        return $this->currentStep;
    }

    /**
     * Transition pending → processing. Starts the step timer.
     * Called when the provider call actually fires.
     */
    public function beginStep(): void
    {
        if ($this->currentStep === null) {
            return;
        }

        $this->stepStartTime = microtime(true);

        $this->currentStep->update([
            'status' => ExecutionStatus::Processing,
            'started_at' => now(),
        ]);
    }

    /**
     * Transition processing → completed. Records duration.
     */
    public function completeStep(): void
    {
        if ($this->currentStep === null) {
            return;
        }

        $durationMs = $this->stepStartTime > 0
            ? (int) ((microtime(true) - $this->stepStartTime) * 1000)
            : null;

        $this->currentStep->markCompleted($durationMs);
        $this->currentStep = null;
    }

    // ─── Tool Call Lifecycle ────────────────────────────────────

    /**
     * Create a tool call record in pending state.
     * Arguments captured immediately — you know what was requested
     * even if the tool never starts.
     */
    public function createToolCall(ToolCall $toolCall, ToolCallType $type = ToolCallType::Atlas): ExecutionToolCall
    {
        if ($this->execution === null || $this->currentStep === null) {
            throw new \RuntimeException('Cannot track a tool call without an active execution and step.');
        }

        $toolCallModel = config('atlas.persistence.models.execution_tool_call', ExecutionToolCall::class);

        $this->currentToolCall = $toolCallModel::create([
            'execution_id' => $this->execution->id,
            'step_id' => $this->currentStep->id,
            'tool_call_id' => $toolCall->id,
            'name' => $toolCall->name,
            'type' => $type,
            'status' => ExecutionStatus::Pending,
            'arguments' => $toolCall->arguments,
        ]);

        return $this->currentToolCall;
    }

    /**
     * Transition pending → processing. Starts the tool timer.
     * Called right before the tool's handle() method executes.
     *
     * @return float Precise start time for duration calculation
     */
    public function beginToolCall(ExecutionToolCall $record): float
    {
        $record->update([
            'status' => ExecutionStatus::Processing,
            'started_at' => now(),
        ]);

        return microtime(true);
    }

    /**
     * Transition processing → completed.
     */
    public function completeToolCall(ExecutionToolCall $record, float $startTime, string $result): void
    {
        $durationMs = (int) ((microtime(true) - $startTime) * 1000);
        $record->markCompleted($result, $durationMs);
        $this->currentToolCall = null;
    }

    /**
     * Transition processing → failed.
     */
    public function failToolCall(ExecutionToolCall $record, float $startTime, string $error): void
    {
        $durationMs = (int) ((microtime(true) - $startTime) * 1000);
        $record->markFailed($error, $durationMs);
        $this->currentToolCall = null;
    }

    // ─── Direct Execution (non-step calls) ────────────────────

    /**
     * Complete execution for non-step calls (direct provider calls).
     * Records tokens directly instead of aggregating from steps.
     */
    public function completeDirectExecution(int $inputTokens = 0, int $outputTokens = 0): void
    {
        if ($this->execution === null) {
            return;
        }

        $durationMs = $this->executionStartTime > 0
            ? (int) ((microtime(true) - $this->executionStartTime) * 1000)
            : null;

        $this->execution->update([
            'status' => ExecutionStatus::Completed,
            'total_input_tokens' => $inputTokens,
            'total_output_tokens' => $outputTokens,
            'completed_at' => now(),
            'duration_ms' => $durationMs,
        ]);
    }

    // ─── Asset Linking ──────────────────────────────────────────

    /**
     * Link a generated asset to the current execution.
     */
    public function linkAsset(int $assetId): void
    {
        if ($this->execution === null) {
            return;
        }

        $this->execution->update(['asset_id' => $assetId]);
    }

    // ─── Accessors ──────────────────────────────────────────────

    /**
     * Whether there's an active execution in this service instance.
     * Used by TrackProviderCall to detect if an agent execution
     * is already tracking this provider call.
     */
    public function hasActiveExecution(): bool
    {
        return $this->execution !== null;
    }

    /**
     * Get the current execution.
     */
    public function getExecution(): ?Execution
    {
        return $this->execution;
    }

    public function currentStep(): ?ExecutionStep
    {
        return $this->currentStep;
    }

    /**
     * Get the current tool call being tracked.
     * Used by ToolAssets to link created assets to the tool call.
     */
    public function getCurrentToolCall(): ?ExecutionToolCall
    {
        return $this->currentToolCall;
    }

    /**
     * Reset state for the next execution (if service is reused).
     */
    public function reset(): void
    {
        $this->execution = null;
        $this->currentStep = null;
        $this->currentToolCall = null;
        $this->stepSequence = 0;
        $this->executionStartTime = 0;
        $this->stepStartTime = 0;
    }
}
