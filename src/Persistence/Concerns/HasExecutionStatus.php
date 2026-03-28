<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Concerns;

use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * Provides common execution status scopes for models using the ExecutionStatus enum.
 *
 * Used by Execution, ExecutionStep, and ExecutionToolCall to avoid duplicating
 * identical scope methods across all three models.
 *
 * Note: scopeQueued is intentionally absent — only Execution has a Queued state.
 * Steps and tool calls skip directly from Pending to Processing.
 */
trait HasExecutionStatus
{
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
