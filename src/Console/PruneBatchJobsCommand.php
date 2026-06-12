<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Console;

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Persistence\Models\BatchGroup;
use Atlasphp\Atlas\Persistence\Models\BatchJob;
use Atlasphp\Atlas\Persistence\Models\BatchResult;
use Illuminate\Console\Command;

/**
 * Deletes batch jobs (and their results) older than the retention period.
 *
 * Batch history accumulates indefinitely — a high-volume app can reach millions
 * of result rows. This command sweeps jobs past `atlas.batch.retention_days`
 * (default 90) in id-ordered chunks, deleting each job's result rows first and
 * then the jobs themselves (explicitly, not relying on a DB-level cascade that
 * varies by driver). Deleting in bounded chunks keeps the sweep from locking
 * the table on large backlogs.
 *
 * Schedule: Schedule::command('atlas:batch-prune')->daily();
 */
class PruneBatchJobsCommand extends Command
{
    protected $signature = 'atlas:batch-prune
        {--days= : Override the retention window in days (default: config value)}
        {--chunk=1000 : Number of jobs to delete per batch}';

    protected $description = 'Delete batch jobs and results older than the retention period';

    public function handle(AtlasConfig $config): int
    {
        if (! $config->persistenceEnabled) {
            $this->info('Persistence is not enabled. Nothing to prune.');

            return self::SUCCESS;
        }

        $days = (int) ($this->option('days') ?? config('atlas.batch.retention_days', 90));
        $chunk = max(1, (int) $this->option('chunk'));
        $cutoff = now()->subDays($days);

        /** @var class-string<BatchJob> $model */
        $model = $config->model('batch_job', BatchJob::class);
        /** @var class-string<BatchResult> $resultModel */
        $resultModel = $config->model('batch_result', BatchResult::class);

        $deleted = 0;

        do {
            $ids = $model::query()
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($chunk)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            // Delete children then parents explicitly — independent of whether
            // the DB enforces the foreign-key cascade.
            $resultModel::query()->whereIn('batch_job_id', $ids)->delete();
            $deleted += $model::query()->whereIn('id', $ids)->delete();
        } while ($ids->count() === $chunk);

        // Sweep groups left empty by the prune (their FK nulls on job delete,
        // so they'd otherwise accumulate forever).
        /** @var class-string<BatchGroup> $groupModel */
        $groupModel = $config->model('batch_group', BatchGroup::class);
        $groups = $groupModel::query()
            ->where('created_at', '<', $cutoff)
            ->whereDoesntHave('jobs')
            ->delete();

        $this->info("Pruned {$deleted} batch job(s) and {$groups} empty group(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
