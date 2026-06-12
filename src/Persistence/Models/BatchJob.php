<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Models;

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Enums\BatchStatus;
use Atlasphp\Atlas\Persistence\Concerns\HasAtlasTable;
use Atlasphp\Atlas\Responses\RequestCounts;
use Atlasphp\Atlas\Responses\Usage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Class BatchJob
 *
 * Tracks one submitted provider batch — its normalized status, aggregate
 * counts, rolled-up usage, and the provider batch id used for polling. Its
 * per-line results live in the batch_results table.
 *
 * @property int $id
 * @property int|null $batch_group_id
 * @property string $provider
 * @property string $modality
 * @property string|null $batch_id
 * @property BatchStatus $status
 * @property int $total
 * @property int $succeeded
 * @property int $failed
 * @property int $processing
 * @property array<string, int>|null $usage
 * @property string|null $input_file_id
 * @property string|null $output_file_id
 * @property string|null $error
 * @property Carbon|null $submitted_at
 * @property Carbon|null $completed_at
 */
class BatchJob extends Model
{
    use HasAtlasTable;

    protected $table = 'batch_jobs';

    protected $fillable = [
        'batch_group_id',
        'provider',
        'modality',
        'batch_id',
        'status',
        'total',
        'succeeded',
        'failed',
        'processing',
        'usage',
        'input_file_id',
        'output_file_id',
        'error',
        'submitted_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BatchStatus::class,
            'usage' => 'array',
            'total' => 'integer',
            'succeeded' => 'integer',
            'failed' => 'integer',
            'processing' => 'integer',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<BatchGroup, $this> */
    public function group(): BelongsTo
    {
        /** @var class-string<BatchGroup> $model */
        $model = app(AtlasConfig::class)->model('batch_group', BatchGroup::class);

        return $this->belongsTo($model, 'batch_group_id');
    }

    /** @return HasMany<BatchResult, $this> */
    public function results(): HasMany
    {
        /** @var class-string<BatchResult> $model */
        $model = app(AtlasConfig::class)->model('batch_result', BatchResult::class);

        return $this->hasMany($model);
    }

    /**
     * Apply a provider status response to this job's columns.
     */
    public function applyStatus(BatchStatus $status, RequestCounts $counts): void
    {
        $this->update([
            'status' => $status,
            'total' => $counts->total,
            'succeeded' => $counts->succeeded,
            'failed' => $counts->failed,
            'processing' => $counts->processing,
        ]);
    }

    /**
     * Mark the job completed with its final counts and rolled-up usage.
     *
     * One write: status, counts, usage, and completed_at together.
     */
    public function markCompleted(RequestCounts $counts, Usage $usage): void
    {
        $this->update([
            'status' => BatchStatus::Completed,
            'total' => $counts->total,
            'succeeded' => $counts->succeeded,
            'failed' => $counts->failed,
            'processing' => $counts->processing,
            'usage' => $usage->toArray(),
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the job failed/expired with a terminal status and message.
     */
    public function markFailed(BatchStatus $status, ?string $error = null): void
    {
        $this->update([
            'status' => $status,
            'error' => $error,
            'completed_at' => now(),
        ]);
    }

    /**
     * Non-terminal jobs the poller should advance.
     *
     * Terminal states are derived from the enum so adding a new one can't drift
     * out of sync with this scope.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $terminal = array_map(
            fn (BatchStatus $status): string => $status->value,
            array_filter(BatchStatus::cases(), fn (BatchStatus $status): bool => $status->isTerminal()),
        );

        $query->whereNotNull('batch_id')->whereNotIn('status', $terminal);
    }

    /** @param Builder<static> $query */
    public function scopeForProvider(Builder $query, string $provider): void
    {
        $query->where('provider', $provider);
    }
}
