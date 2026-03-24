<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Models;

use Atlasphp\Atlas\Database\Factories\ExecutionToolCallFactory;
use Atlasphp\Atlas\Persistence\Concerns\HasAtlasTable;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ToolCallType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class ExecutionToolCall
 *
 * Tracks an individual tool invocation within an execution step. Records arguments,
 * result, timing, and status for full tool call observability.
 *
 * @property int $id
 * @property int $execution_id
 * @property int|null $step_id
 * @property string $tool_call_id
 * @property string $name
 * @property ToolCallType $type
 * @property ExecutionStatus $status
 * @property array<mixed>|null $arguments
 * @property string|null $result
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property int|null $duration_ms
 * @property array<mixed>|null $metadata
 */
class ExecutionToolCall extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasAtlasTable, HasFactory;

    protected static function newFactory(): ExecutionToolCallFactory
    {
        return ExecutionToolCallFactory::new();
    }

    protected $table = 'execution_tool_calls';

    protected $fillable = [
        'execution_id',
        'step_id',
        'tool_call_id',
        'name',
        'type',
        'status',
        'arguments',
        'result',
        'started_at',
        'completed_at',
        'duration_ms',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => ToolCallType::class,
            'status' => ExecutionStatus::class,
            'arguments' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'duration_ms' => 'integer',
            'metadata' => 'array',
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

    /** @return BelongsTo<ExecutionStep, $this> */
    public function step(): BelongsTo
    {
        /** @var class-string<ExecutionStep> $model */
        $model = config('atlas.persistence.models.execution_step', ExecutionStep::class);

        return $this->belongsTo($model, 'step_id');
    }

    // ─── Lifecycle ──────────────────────────────────────────────

    /**
     * Record tool completion (success).
     */
    public function markCompleted(string $result, int $durationMs): void
    {
        $this->update([
            'status' => ExecutionStatus::Completed,
            'result' => $result,
            'completed_at' => now(),
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * Record tool failure.
     */
    public function markFailed(string $error, int $durationMs): void
    {
        $this->update([
            'status' => ExecutionStatus::Failed,
            'result' => $error,
            'completed_at' => now(),
            'duration_ms' => $durationMs,
        ]);
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

    /** @param Builder<static> $query */
    public function scopeForTool(Builder $query, string $name): void
    {
        $query->where('name', $name);
    }
}
