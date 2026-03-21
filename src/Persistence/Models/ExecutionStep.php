<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Models;

use Atlasphp\Atlas\Database\Factories\ExecutionStepFactory;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Persistence\Concerns\HasAtlasTable;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Class ExecutionStep
 *
 * Represents a single provider call within an execution. Records content, reasoning,
 * token usage, and finish reason. Owns tool calls triggered by the provider response.
 *
 * @property int $id
 * @property int $execution_id
 * @property int $sequence
 * @property ExecutionStatus $status
 * @property string|null $content
 * @property string|null $reasoning
 * @property int $input_tokens
 * @property int $output_tokens
 * @property string|null $finish_reason
 * @property string|null $error
 * @property array<mixed>|null $metadata
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property int|null $duration_ms
 */
class ExecutionStep extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasAtlasTable, HasFactory;

    protected static function newFactory(): ExecutionStepFactory
    {
        return ExecutionStepFactory::new();
    }

    protected $table = 'execution_steps';

    protected $fillable = [
        'execution_id',
        'sequence',
        'status',
        'content',
        'reasoning',
        'input_tokens',
        'output_tokens',
        'finish_reason',
        'error',
        'metadata',
        'started_at',
        'completed_at',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'status' => ExecutionStatus::class,
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'duration_ms' => 'integer',
        ];
    }

    // ─── Relationships ──────────────────────────────────────────

    /** @return BelongsTo<Execution, $this> */
    public function execution(): BelongsTo
    {
        /** @var class-string<Execution> $model */
        $model = config('atlas.persistence.models.execution', Execution::class);

        return $this->belongsTo($model);
    }

    /** @return HasMany<ExecutionToolCall, $this> */
    public function toolCalls(): HasMany
    {
        /** @var class-string<ExecutionToolCall> $model */
        $model = config('atlas.persistence.models.execution_tool_call', ExecutionToolCall::class);

        return $this->hasMany($model, 'step_id');
    }

    // ─── Lifecycle ──────────────────────────────────────────────

    /**
     * Record the provider response data on this step.
     * Status stays processing — tools may still need to run.
     */
    public function recordResponse(
        ?string $content,
        ?string $reasoning,
        int $inputTokens,
        int $outputTokens,
        string $finishReason,
    ): void {
        $this->update([
            'content' => $content,
            'reasoning' => $reasoning,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'finish_reason' => $finishReason,
        ]);
    }

    /**
     * Mark this step as completed (all tool calls finished, ready for next step).
     */
    public function markCompleted(?int $durationMs): void
    {
        $this->update([
            'status' => ExecutionStatus::Completed,
            'completed_at' => now(),
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * Mark this step as failed (provider error, timeout, etc.).
     */
    public function markFailed(string $error, ?int $durationMs): void
    {
        $this->update([
            'status' => ExecutionStatus::Failed,
            'error' => $error,
            'completed_at' => now(),
            'duration_ms' => $durationMs,
        ]);
    }

    // ─── Query Helpers ──────────────────────────────────────────

    public function hasToolCalls(): bool
    {
        return $this->finish_reason === FinishReason::ToolCalls->value;
    }

    public function getTotalTokensAttribute(): int
    {
        return $this->input_tokens + $this->output_tokens;
    }

    // ─── Scopes ─────────────────────────────────────────────────

    /** @param Builder<static> $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', ExecutionStatus::Pending);
    }

    /** @param Builder<static> $query */
    public function scopeProcessing(Builder $query): void
    {
        $query->where('status', ExecutionStatus::Processing);
    }

    /** @param Builder<static> $query */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', ExecutionStatus::Completed);
    }

    /** @param Builder<static> $query */
    public function scopeFailed(Builder $query): void
    {
        $query->where('status', ExecutionStatus::Failed);
    }
}
