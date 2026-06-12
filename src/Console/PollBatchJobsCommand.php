<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Console;

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Batch\BatchService;
use Atlasphp\Atlas\Persistence\Models\BatchJob;
use Illuminate\Console\Command;

/**
 * Polls open batch jobs and hydrates completed ones.
 *
 * Batch results are delivered by polling — the provider has no callback into
 * your app. This command advances every non-terminal tracked job: it fetches
 * the current status, updates counts, and on completion stores the per-line
 * results. Idempotent and safe to run on a schedule.
 *
 * Schedule: $schedule->command('atlas:batch-poll')->everyFiveMinutes();
 */
class PollBatchJobsCommand extends Command
{
    protected $signature = 'atlas:batch-poll
        {--limit= : Maximum number of open jobs to poll this run (default: config value)}
        {--provider= : Only poll jobs for this provider}';

    protected $description = 'Poll open batch jobs and hydrate completed ones';

    public function handle(BatchService $service, AtlasConfig $config): int
    {
        if (! $config->persistenceEnabled) {
            $this->info('Persistence is not enabled. Batch polling requires persistence.');

            return self::SUCCESS;
        }

        /** @var class-string<BatchJob> $model */
        $model = $config->model('batch_job', BatchJob::class);

        $limit = (int) ($this->option('limit') ?? config('atlas.batch.poll_limit', 25));

        $query = $model::query()->open()->limit($limit);

        if (is_string($provider = $this->option('provider')) && $provider !== '') {
            $query->forProvider($provider);
        }

        $jobs = $query->get();

        if ($jobs->isEmpty()) {
            $this->info('No open batch jobs to poll.');

            return self::SUCCESS;
        }

        foreach ($jobs as $job) {
            $service->syncFromProvider($job);
        }

        $this->info("Polled {$jobs->count()} batch job(s).");

        return self::SUCCESS;
    }
}
