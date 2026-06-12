<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Models;

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Persistence\Concerns\HasAtlasTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Class BatchGroup
 *
 * Ties multiple batch jobs into one logical operation (e.g. 4,000 images split
 * into four batches), so progress can be reported across all of them.
 *
 * @property int $id
 * @property string|null $label
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BatchGroup extends Model
{
    use HasAtlasTable;

    protected $table = 'batch_groups';

    protected $fillable = [
        'label',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /** @return HasMany<BatchJob, $this> */
    public function jobs(): HasMany
    {
        /** @var class-string<BatchJob> $model */
        $model = app(AtlasConfig::class)->model('batch_job', BatchJob::class);

        return $this->hasMany($model, 'batch_group_id');
    }

    /**
     * Aggregate progress across all jobs in the group.
     *
     * @return array{total: int, succeeded: int, failed: int, jobs: int, completed_jobs: int}
     */
    public function progress(): array
    {
        $jobs = $this->jobs()->get(['status', 'total', 'succeeded', 'failed']);

        return [
            'total' => (int) $jobs->sum('total'),
            'succeeded' => (int) $jobs->sum('succeeded'),
            'failed' => (int) $jobs->sum('failed'),
            'jobs' => $jobs->count(),
            'completed_jobs' => $jobs->filter(fn (BatchJob $j) => $j->status->isTerminal())->count(),
        ];
    }

    /**
     * Whether every job in the group has reached a terminal state.
     */
    public function isComplete(): bool
    {
        $progress = $this->progress();

        return $progress['jobs'] > 0 && $progress['jobs'] === $progress['completed_jobs'];
    }
}
