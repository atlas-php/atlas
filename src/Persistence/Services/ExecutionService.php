<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Services;

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Enums\ToolCallType;
use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Models\ConversationMessage;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\ExecutionStep;
use Atlasphp\Atlas\Persistence\Models\ExecutionToolCall;
use Atlasphp\Atlas\Providers\Google\GoogleToolCall;
use Atlasphp\Atlas\Responses\Usage;
use Illuminate\Support\Facades\DB;

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

    protected ?Asset $lastAsset = null;

    protected int $stepSequence = 0;

    /** @var float Precise start time for execution duration */
    protected float $executionStartTime = 0;

    /** @var float Precise start time for current step duration */
    protected float $stepStartTime = 0;

    /**
     * Snapshots of the active execution context, pushed when a sub-agent run
     * creates a nested execution and popped when it completes. Lets the single
     * scoped service track a parent → child delegation tree without losing the
     * parent's in-flight state.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $contextStack = [];

    /** @var class-string<Execution> */
    private readonly string $executionModel;

    /** @var class-string<ExecutionStep> */
    private readonly string $stepModel;

    /** @var class-string<ExecutionToolCall> */
    private readonly string $toolCallModel;

    public function __construct()
    {
        $this->executionModel = app(AtlasConfig::class)->model('execution', Execution::class);
        $this->stepModel = app(AtlasConfig::class)->model('execution_step', ExecutionStep::class);
        $this->toolCallModel = app(AtlasConfig::class)->model('execution_tool_call', ExecutionToolCall::class);
    }

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
        $executionModel = $this->executionModel;

        // A new execution created while another is mid-tool-call is a nested
        // (sub-agent) run: the sub-agent runs inside the parent's delegating
        // tool call. Capture the parent link + a snapshot to restore on
        // completion. (A merely-active-but-idle execution from a prior run is
        // NOT nesting — gating on currentToolCall avoids that false positive.)
        $nested = $this->execution !== null && $this->currentToolCall !== null;
        $snapshot = $nested ? $this->snapshotContext() : null;
        $parentExecutionId = $nested ? $this->execution->id : null;
        $parentToolCallId = $nested ? $this->currentToolCall->id : null;
        $depth = $nested ? $this->execution->depth + 1 : 0;

        $execution = $executionModel::create([
            'conversation_id' => $conversationId,
            'parent_execution_id' => $parentExecutionId,
            'parent_tool_call_id' => $parentToolCallId,
            'depth' => $depth,
            'agent' => $agent,
            'type' => $type ?? ExecutionType::Text,
            'provider' => $provider,
            'model' => $model,
            'status' => ExecutionStatus::Pending,
            'metadata' => ! empty($meta) ? $this->filterMetaForStorage($meta) : null,
        ]);

        // Only mutate service state once the child record exists, so a failed
        // create leaves the parent context fully intact (no leaked snapshot).
        if ($snapshot !== null) {
            $this->contextStack[] = $snapshot;
        }

        $this->execution = $execution;

        // Link the trigger message to this execution (message owns the FK)
        if ($messageId !== null) {
            $messageModel = app(AtlasConfig::class)->model('conversation_message', ConversationMessage::class);
            $messageModel::where('id', $messageId)->update(['execution_id' => $this->execution->id]);
        }

        $this->stepSequence = 1;

        return $this->execution;
    }

    /**
     * Adopt a pre-created execution record (from queue dispatch).
     * Updates the record with agent context and sets it as the active execution.
     */
    public function adoptExecution(
        int $id,
        string $provider,
        string $model,
        ?string $agent = null,
        ?int $conversationId = null,
        ?ExecutionType $type = null,
    ): Execution {
        $executionModel = $this->executionModel;

        $this->execution = $executionModel::findOrFail($id);

        // Update with agent context that wasn't available at queue dispatch time
        $this->execution->update(array_filter([
            'provider' => $provider,
            'model' => $model,
            'agent' => $agent,
            'conversation_id' => $conversationId,
            'type' => $type ?? $this->execution->type,
        ], fn ($v) => $v !== null));

        $this->stepSequence = 1;

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
     * Transition processing → completed. Records usage and duration.
     */
    public function completeExecution(?Usage $usage = null): void
    {
        if ($this->execution === null) {
            return;
        }

        $this->execution->markCompleted($this->elapsedMs($this->executionStartTime), $usage);

        $this->restoreParentContext();
    }

    /**
     * Transition processing → failed. Records error, marks in-flight step failed too.
     */
    public function failExecution(\Throwable $exception): void
    {
        if ($this->execution === null) {
            return;
        }

        $durationMs = $this->elapsedMs($this->executionStartTime);

        // Mark the in-flight step as failed too — you know exactly where it died
        if ($this->currentStep?->status === ExecutionStatus::Processing) {
            $this->currentStep->markFailed($exception->getMessage(), $this->elapsedMs($this->stepStartTime));
        }

        $this->execution->markFailed(
            get_class($exception).': '.$exception->getMessage(),
            $durationMs,
        );

        $this->restoreParentContext();
    }

    /**
     * Capture the active execution context for later restoration.
     *
     * @return array<string, mixed>
     */
    protected function snapshotContext(): array
    {
        return [
            'execution' => $this->execution,
            'currentStep' => $this->currentStep,
            'currentToolCall' => $this->currentToolCall,
            'lastAsset' => $this->lastAsset,
            'stepSequence' => $this->stepSequence,
            'executionStartTime' => $this->executionStartTime,
            'stepStartTime' => $this->stepStartTime,
        ];
    }

    /**
     * Restore the parent execution context after a nested sub-agent run.
     * No-op when not nested (the stack is empty).
     */
    protected function restoreParentContext(): void
    {
        $snapshot = array_pop($this->contextStack);

        if ($snapshot === null) {
            return;
        }

        $this->execution = $snapshot['execution'] instanceof Execution ? $snapshot['execution'] : null;
        $this->currentStep = $snapshot['currentStep'] instanceof ExecutionStep ? $snapshot['currentStep'] : null;
        $this->currentToolCall = $snapshot['currentToolCall'] instanceof ExecutionToolCall ? $snapshot['currentToolCall'] : null;
        $this->lastAsset = $snapshot['lastAsset'] instanceof Asset ? $snapshot['lastAsset'] : null;
        $this->stepSequence = (int) $snapshot['stepSequence'];
        $this->executionStartTime = (float) $snapshot['executionStartTime'];
        $this->stepStartTime = (float) $snapshot['stepStartTime'];
    }

    // ─── Step Lifecycle ─────────────────────────────────────────

    /**
     * Create a new step in pending state. Called BEFORE the provider call.
     *
     * @param  array<string, mixed>  $meta
     */
    public function createStep(array $meta = []): ExecutionStep
    {
        if ($this->execution === null) {
            throw new \RuntimeException('Cannot create a step without an active execution.');
        }

        $stepModel = $this->stepModel;

        $this->currentStep = $stepModel::create([
            'execution_id' => $this->execution->id,
            'sequence' => $this->stepSequence++,
            'status' => ExecutionStatus::Pending,
            'metadata' => $meta !== [] ? $meta : null,
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

        $this->currentStep->markCompleted($this->elapsedMs($this->stepStartTime));

        // Do NOT null currentStep here — tool execution happens AFTER
        // the step middleware completes. TrackToolCall needs currentStep
        // to link tool calls to their step. The reference is cleared
        // when createStep() starts the next step or reset() is called.
    }

    // ─── Tool Call Lifecycle ────────────────────────────────────

    /**
     * Create a tool call record in pending state.
     * Arguments captured immediately — you know what was requested
     * even if the tool never starts.
     *
     * @param  array<string, mixed>  $meta
     */
    public function createToolCall(ToolCall $toolCall, ToolCallType $type = ToolCallType::Local, array $meta = []): ExecutionToolCall
    {
        if ($this->execution === null || $this->currentStep === null) {
            throw new \RuntimeException('Cannot track a tool call without an active execution and step.');
        }

        $toolCallModel = $this->toolCallModel;
        $metadata = $this->filterMetaForStorage($this->toolCallMetadata($toolCall, $meta));

        $this->currentToolCall = $toolCallModel::create([
            'execution_id' => $this->execution->id,
            'step_id' => $this->currentStep->id,
            'tool_call_id' => $toolCall->id,
            'name' => $toolCall->name,
            'type' => $type,
            'status' => ExecutionStatus::Pending,
            'arguments' => $toolCall->arguments,
            'metadata' => $metadata,
        ]);

        return $this->currentToolCall;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function toolCallMetadata(ToolCall $toolCall, array $meta): array
    {
        if ($toolCall instanceof GoogleToolCall && $toolCall->thoughtSignature !== null) {
            $meta[GoogleToolCall::THOUGHT_SIGNATURE_METADATA_KEY] = $toolCall->thoughtSignature;
        }

        return $meta;
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
        $record->markCompleted($result, $this->elapsedMs($startTime) ?? 0);
        $this->currentToolCall = null;
    }

    /**
     * Transition processing → failed.
     */
    public function failToolCall(ExecutionToolCall $record, float $startTime, string $error): void
    {
        $record->markFailed($error, $this->elapsedMs($startTime) ?? 0);
        $this->currentToolCall = null;
    }

    // ─── Direct Execution (non-step calls) ────────────────────

    /**
     * Complete execution for non-step calls (direct provider calls).
     * Records usage directly instead of aggregating from steps.
     */
    public function completeDirectExecution(?Usage $usage = null): void
    {
        if ($this->execution === null) {
            return;
        }

        $this->execution->update([
            'status' => ExecutionStatus::Completed,
            'usage' => $usage?->toArray(),
            'completed_at' => now(),
            'duration_ms' => $this->elapsedMs($this->executionStartTime),
        ]);

        // Symmetry with complete/failExecution: pop any nested context. A no-op
        // when not nested (empty stack), so the next run starts clean.
        $this->restoreParentContext();
    }

    // ─── Asset Linking ──────────────────────────────────────────

    /**
     * Track the last asset created during this execution.
     * The asset already has execution_id set at creation time.
     */
    public function linkAsset(?Asset $asset): void
    {
        $this->lastAsset = $asset;
    }

    /**
     * Get the last asset stored during this execution.
     * Available immediately after a media provider call completes.
     */
    public function getLastAsset(): ?Asset
    {
        return $this->lastAsset;
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

    // ─── Voice Execution ────────────────────────────────────────

    /**
     * Complete a voice execution by execution ID.
     *
     * Uses atomic update guarded by status=Processing to prevent race
     * conditions when transcript and close requests arrive concurrently.
     * No-op if the execution is not found or already completed.
     *
     * @param  array<string, mixed>|null  $extraMeta
     */
    public function completeVoiceExecution(int $executionId, ?array $extraMeta = null): ?Execution
    {
        $executionModel = $this->executionModel;

        /** @var Execution|null $execution */
        $execution = $executionModel::where('id', $executionId)
            ->where('status', ExecutionStatus::Processing)
            ->first();

        if ($execution === null) {
            return null;
        }

        $durationMs = $execution->started_at !== null
            ? (int) abs(now()->diffInMilliseconds($execution->started_at))
            : null;

        // Single atomic update — status + metadata in one statement.
        // The WHERE status=Processing guard makes this race-safe.
        $updateData = [
            'status' => ExecutionStatus::Completed,
            'completed_at' => now(),
            'duration_ms' => $durationMs,
        ];

        if ($extraMeta !== null) {
            $updateData['metadata'] = array_merge($execution->metadata ?? [], $extraMeta);
        }

        $affected = DB::transaction(function () use ($executionModel, $execution, $updateData): int {
            return $executionModel::where('id', $execution->id)
                ->where('status', ExecutionStatus::Processing)
                ->update($updateData);
        });

        if ($affected === 0) {
            return null;
        }

        $execution->refresh();

        return $execution;
    }

    /**
     * Reset state for the next execution (if service is reused).
     */
    public function reset(): void
    {
        $this->execution = null;
        $this->currentStep = null;
        $this->currentToolCall = null;
        $this->lastAsset = null;
        $this->stepSequence = 1;
        $this->executionStartTime = 0;
        $this->stepStartTime = 0;
        $this->contextStack = [];
    }

    /**
     * Calculate elapsed milliseconds from a start time, or null if not started.
     */
    private function elapsedMs(float $startTime): ?int
    {
        return $startTime > 0
            ? (int) ((microtime(true) - $startTime) * 1000)
            : null;
    }

    /**
     * Filter meta for database storage — remove internal runtime keys
     * (prefixed with underscore) that are for middleware communication only.
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>|null
     */
    private function filterMetaForStorage(array $meta): ?array
    {
        $filtered = array_filter(
            $meta,
            fn (mixed $value, string $key): bool => ! str_starts_with($key, '_'),
            ARRAY_FILTER_USE_BOTH,
        );

        return $filtered !== [] ? $filtered : null;
    }
}
