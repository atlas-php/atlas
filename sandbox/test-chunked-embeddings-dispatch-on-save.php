<?php

declare(strict_types=1);

/**
 * Chunked Embeddings — Dispatch-on-Save + Debounce End-to-End Test
 *
 * Validates the v3.1.1 dispatch path against a real Postgres + database
 * queue + (optional) OpenAI provider. Where test-chunked-embeddings.php
 * exercises the synchronous reconciler, this script exercises the path
 * a real consumer would use in production: save the model, let the
 * trait dispatch the job, let the queue worker process it.
 *
 * Sections:
 *   1. Single save dispatches exactly one ChunkContentJob with the
 *      configured settle delay.
 *   2. ShouldBeUnique collapses an 11-save burst into one queued job.
 *   3. Different rows get separate unique jobs (uniqueId is per-row).
 *   4. dispatch_on_save = false suppresses dispatch entirely.
 *   5. Queue worker processes the job past the settle window and
 *      chunks land in atlas_chunks.
 *   6. Debounce: a save mid-window causes the worker to release the
 *      job back to the queue instead of chunking against fresh content.
 *
 * Usage:
 *   cd sandbox
 *   php artisan migrate:fresh           # one-time / schema reset
 *   php test-chunked-embeddings-dispatch-on-save.php
 *
 * Requires:
 *   - DB_CONNECTION=pgsql with pgvector
 *   - QUEUE_CONNECTION=database (the script inspects the `jobs` table)
 *   - OPENAI_API_KEY in sandbox/.env for section 5's embed call
 *
 * The script DOES NOT need an external `queue:work` running — it
 * processes jobs inline via Artisan::call('queue:work --once').
 */
$app = require __DIR__.'/bootstrap.php';

use App\Models\Project;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Persistence\Models\Chunk;
use Atlasphp\Atlas\Queue\Jobs\ChunkContentJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$app['config']->set('atlas.defaults.embed', [
    'provider' => 'openai',
    'model' => env('ATLAS_EMBED_MODEL', 'text-embedding-3-small'),
]);
$app['config']->set('atlas.providers.openai', [
    'api_key' => env('OPENAI_API_KEY'),
    'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
]);
// Tight settle so the queue worker can drain in a reasonable test runtime.
$app['config']->set('atlas.embeddings.sweep_settle', 3);
$app['config']->set('atlas.embeddings.dispatch_on_save', true);
$app['config']->set('atlas.embeddings.chunk_size', 200);
AtlasConfig::refresh();

// ─── Preflight ──────────────────────────────────────────────────────────────

if (DB::connection()->getDriverName() !== 'pgsql') {
    echo "This test requires PostgreSQL (DB_CONNECTION=pgsql). Aborting.\n";
    exit(1);
}

if (config('queue.default') !== 'database') {
    echo "This test requires QUEUE_CONNECTION=database (it inspects the `jobs` table).\n";
    echo 'Current: '.config('queue.default')."\n";
    exit(1);
}

if (! Schema::hasTable('projects') || ! Schema::hasTable('atlas_chunks') || ! Schema::hasTable('jobs')) {
    echo "Missing tables — run `php artisan migrate:fresh` first.\n";
    exit(1);
}

// ─── Helpers ────────────────────────────────────────────────────────────────

$failures = [];

function section(string $title): void
{
    echo "\n".str_repeat('─', 76)."\n  {$title}\n".str_repeat('─', 76)."\n";
}

function check(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    $mark = $ok ? '✓' : '✗';
    $line = "  {$mark} {$name}";
    if ($detail !== '') {
        $line .= " — {$detail}";
    }
    echo $line."\n";
    if (! $ok) {
        $failures[] = $name.($detail !== '' ? ": {$detail}" : '');
    }
}

function queuedChunkJobs(): int
{
    return (int) DB::table('jobs')
        ->where('payload', 'like', '%ChunkContentJob%')
        ->count();
}

function clearAll(): void
{
    DB::table('atlas_chunks')->delete();
    DB::table('projects')->delete();
    DB::table('jobs')->delete();
    DB::table('failed_jobs')->delete();
    // Clear any lingering unique-lock keys from a prior run.
    Cache::flush();
}

// ─── 1. Single save dispatches one delayed job ──────────────────────────────

section('1. Single save dispatches exactly one ChunkContentJob (with delay)');

clearAll();
$now = time();

$project = Project::create([
    'title' => 'Single save',
    'body' => 'A body that is long enough to chunk. '.str_repeat('Some prose content. ', 20),
]);

$jobs = DB::table('jobs')->where('payload', 'like', '%ChunkContentJob%')->get();
check('exactly one ChunkContentJob is queued', $jobs->count() === 1, "got {$jobs->count()}");

if ($jobs->isNotEmpty()) {
    $job = $jobs->first();
    $payload = json_decode($job->payload, true);
    $expectedClass = 'Atlasphp\\Atlas\\Queue\\Jobs\\ChunkContentJob';
    $actualClass = $payload['displayName'] ?? $payload['data']['commandName'] ?? null;
    check('job class is ChunkContentJob', $actualClass === $expectedClass, "got: {$actualClass}");

    // Verify the delay is approximately sweep_settle seconds.
    $delay = $job->available_at - $now;
    check('job is delayed approximately sweep_settle seconds',
        $delay >= 2 && $delay <= 5,
        "delay = {$delay}s (expected ~3s)");
}

// ─── 2. ShouldBeUnique collapses many rapid saves ───────────────────────────

section('2. ShouldBeUnique collapses an 11-save burst into one queued job');

clearAll();

$project = Project::create(['title' => 'Burst project', 'body' => 'edit 0 — '.str_repeat('words ', 30)]);
for ($i = 1; $i <= 10; $i++) {
    $project->update(['body' => "edit {$i} — ".str_repeat('words ', 30)]);
}

$count = queuedChunkJobs();
check('only one job queued despite 11 saves', $count === 1, "got {$count}");

// ─── 3. Separate rows get separate unique jobs ──────────────────────────────

section('3. Separate rows get separate unique jobs (uniqueId is per-row)');

clearAll();

$a = Project::create(['title' => 'Project A', 'body' => 'A '.str_repeat('words ', 30)]);
$b = Project::create(['title' => 'Project B', 'body' => 'B '.str_repeat('words ', 30)]);
$c = Project::create(['title' => 'Project C', 'body' => 'C '.str_repeat('words ', 30)]);

$count = queuedChunkJobs();
check('three separate jobs queued', $count === 3, "got {$count}");

// ─── 4. dispatch_on_save = false suppresses dispatch ────────────────────────

section('4. dispatch_on_save = false suppresses dispatch entirely');

clearAll();
config(['atlas.embeddings.dispatch_on_save' => false]);
AtlasConfig::refresh();

Project::create(['title' => 'No-dispatch', 'body' => 'content '.str_repeat('words ', 30)]);

$count = queuedChunkJobs();
check('no jobs queued when dispatch_on_save is false', $count === 0, "got {$count}");

config(['atlas.embeddings.dispatch_on_save' => true]);
AtlasConfig::refresh();

// ─── 5. Queue worker processes and chunks land ──────────────────────────────

section('5. Queue worker processes the job past the settle window');

clearAll();

$project = Project::create([
    'title' => 'End-to-end project',
    'body' => 'A multi-paragraph body that the chunker should produce real chunks for. '
        .str_repeat('The architecture uses event sourcing and the projection rebuilds state on the fly. ', 5)
        .'This is enough text to verify embedding lands in atlas_chunks.',
]);

check('one ChunkContentJob queued for the new project', queuedChunkJobs() === 1);

// Sleep past the settle window so the worker can claim the job.
echo '  waiting '.(config('atlas.embeddings.sweep_settle') + 1)."s for the settle window to elapse...\n";
sleep((int) config('atlas.embeddings.sweep_settle') + 1);

// Process the queue until empty (max 30s wall-clock).
Artisan::call('queue:work', [
    '--once' => true,
    '--stop-when-empty' => true,
    '--timeout' => 30,
]);

$project->refresh();
$chunkCount = Chunk::query()->where('chunkable_id', $project->id)->count();

check('project indexed_hash matches content_hash after processing',
    $project->indexed_hash === $project->content_hash,
    "indexed_hash={$project->indexed_hash}");
check('atlas_chunks has rows for this project', $chunkCount > 0, "got {$chunkCount}");
check('queue is empty after processing', queuedChunkJobs() === 0);

// ─── 6. Debounce: mid-window save causes the worker to release ──────────────

section('6. Debounce — mid-window save causes the worker to release, not embed');

clearAll();
// Stretch the settle so we have headroom to interleave timing.
config(['atlas.embeddings.sweep_settle' => 8]);
AtlasConfig::refresh();

$project = Project::create([
    'title' => 'Debounce project',
    'body' => 'Initial body — '.str_repeat('words ', 30),
]);

// Wait long enough for the original delay to pass, then save again JUST
// before processing so updated_at is fresh and the debounce engages.
echo "  waiting 9s for the initial delay window...\n";
sleep(9);

// Mid-window edit: now updated_at = ~0s ago. The job will fire (available_at
// has passed) but the worker should release it again because the row was
// just touched.
$project->update(['body' => 'Mid-window edit — '.str_repeat('words ', 30)]);
echo "  saved again mid-window; updated_at is fresh\n";

// Process one job — the worker should release the existing job, not embed.
$attemptsBefore = (int) (DB::table('jobs')->where('payload', 'like', '%ChunkContentJob%')->value('attempts') ?? 0);

Artisan::call('queue:work', [
    '--once' => true,
    '--stop-when-empty' => false,
    '--timeout' => 5,
]);

$attemptsAfter = (int) (DB::table('jobs')->where('payload', 'like', '%ChunkContentJob%')->value('attempts') ?? 0);
$stillQueued = queuedChunkJobs();

check('job still in queue after worker run (was released, not completed)',
    $stillQueued === 1,
    "got {$stillQueued} job(s) queued; attempts before={$attemptsBefore}, after={$attemptsAfter}");

// Now wait for the settle window to clear and let the worker finish it.
echo "  waiting 9s for the settle window to clear...\n";
sleep(9);

Artisan::call('queue:work', [
    '--once' => true,
    '--stop-when-empty' => true,
    '--timeout' => 30,
]);

$project->refresh();
$chunkCount = Chunk::query()->where('chunkable_id', $project->id)->count();

check('project indexed after the settle window passed',
    $project->indexed_hash === $project->content_hash);
check('atlas_chunks populated for the debounced project', $chunkCount > 0, "got {$chunkCount}");
check('queue drained after final embed', queuedChunkJobs() === 0);

// ─── 7. Transaction safety across every cache driver ───────────────────────
//
// The v3.1.1 regression: a chunkable save wrapped in DB::transaction()
// dispatched ChunkContentJob from the trait's `saved` hook → ShouldBeUnique
// acquired its lock via the configured cache → with the database cache on
// Postgres, the INSERT-then-fallback-UPDATE pattern aborted the wrapping
// transaction with SQLSTATE 25P02.
//
// The fix wraps the entire dispatch in `Connection::afterCommit(...)` so the
// lock acquisition always runs OUTSIDE the wrapping transaction. The
// behavior must be correct for every cache driver — database (the broken
// case), redis (most prod consumers), file, and array (test setups).

$originalCacheStore = config('cache.default');

// Configure additional cache stores inline so the script is self-contained
// (the sandbox's config/cache.php only ships database + array). These are
// the standard Laravel store configs, copied from Laravel's default
// config/cache.php template.
$app['config']->set('cache.stores.file', [
    'driver' => 'file',
    'path' => storage_path('framework/cache/data'),
    'lock_path' => storage_path('framework/cache/data'),
]);
$app['config']->set('cache.stores.redis', [
    'driver' => 'redis',
    'connection' => 'cache',
    'lock_connection' => 'default',
]);
// Redis connection for cache + lock. Reads from REDIS_HOST/REDIS_PORT
// env if set, defaults to localhost.
$app['config']->set('database.redis.client', 'phpredis');
$app['config']->set('database.redis.options', ['cluster' => 'redis']);
$app['config']->set('database.redis.default', [
    'url' => null,
    'host' => env('REDIS_HOST', '127.0.0.1'),
    'username' => env('REDIS_USERNAME'),
    'password' => env('REDIS_PASSWORD'),
    'port' => env('REDIS_PORT', '6379'),
    'database' => env('REDIS_DB', '0'),
]);
$app['config']->set('database.redis.cache', [
    'url' => null,
    'host' => env('REDIS_HOST', '127.0.0.1'),
    'username' => env('REDIS_USERNAME'),
    'password' => env('REDIS_PASSWORD'),
    'port' => env('REDIS_PORT', '6379'),
    'database' => env('REDIS_CACHE_DB', '1'),
]);

@mkdir(storage_path('framework/cache/data'), 0755, true);

$cacheDriversToTest = ['database', 'redis', 'file', 'array'];

/**
 * Run the prod-equivalent scenario for the given cache driver:
 *   - reset state, switch the cache driver, refresh config
 *   - (database driver only) pre-seed a colliding lock to force the bug
 *   - save a chunkable inside DB::transaction()
 *   - assert the save completed and the row was persisted
 *
 * @return array{threw: ?Throwable, project: ?Project}
 */
function runTransactionSaveScenario(string $cacheDriver, $app, bool $forceLockContention): array
{
    DB::statement('TRUNCATE TABLE projects RESTART IDENTITY CASCADE');
    DB::table('atlas_chunks')->delete();
    DB::table('jobs')->delete();
    if (Schema::hasTable('cache_locks')) {
        DB::table('cache_locks')->delete();
    }
    Cache::flush();

    $app['config']->set('cache.default', $cacheDriver);
    AtlasConfig::refresh();

    if ($forceLockContention && $cacheDriver === 'database') {
        // Pre-seed a colliding lock with a different owner. Without this,
        // the first INSERT into cache_locks succeeds and the bug doesn't
        // surface. In production the lock pre-exists because the previous
        // dispatch held it (`uniqueFor = 3600`s lingering locks). The
        // sandbox cache prefix is the Laravel default for the database
        // driver — see config('cache.prefix') in the sandbox.
        $prefix = (string) ($app['config']->get('cache.prefix') ?: $app['config']->get('cache.stores.database.prefix') ?: '');
        DB::table('cache_locks')->insert([
            'key' => $prefix.'laravel_unique_job:Atlasphp\\Atlas\\Queue\\Jobs\\ChunkContentJob:App\\Models\\Project:1',
            'owner' => 'sandbox-pre-seed-different-owner',
            'expiration' => time() + 3600,
        ]);
    }

    $threw = null;
    $project = null;
    try {
        $project = DB::transaction(function () use ($cacheDriver) {
            // Identical pattern to rundesk's ProjectPageService::update():
            // chunkable save inside an explicit DB transaction.
            return Project::create([
                'title' => "Inside transaction (cache: {$cacheDriver})",
                'body' => 'A body that is long enough to chunk. '.str_repeat('words ', 30),
            ]);
        });
    } catch (Throwable $e) {
        $threw = $e;
    }

    return ['threw' => $threw, 'project' => $project];
}

foreach ($cacheDriversToTest as $driver) {
    section("7. Save inside DB::transaction with cache.default = '{$driver}'");

    // Skip drivers that aren't actually available in this sandbox.
    if ($driver === 'redis') {
        try {
            $app->make('redis')->connection()->ping();
        } catch (Throwable $e) {
            echo "  Redis not reachable — skipping. ({$e->getMessage()})\n";

            continue;
        }
    }
    if ($driver === 'database' && ! Schema::hasTable('cache_locks')) {
        echo "  cache_locks table missing — skipping.\n";

        continue;
    }

    // For the database driver, force the lock-contention scenario that
    // triggered the prod bug. Other drivers don't have the SQL-transaction
    // interaction, but we still run the scenario to prove afterCommit
    // doesn't break anything for them.
    $result = runTransactionSaveScenario($driver, $app, forceLockContention: true);

    check("[{$driver}] save inside DB::transaction completed without throwing",
        $result['threw'] === null,
        $result['threw'] !== null ? get_class($result['threw']).': '.$result['threw']->getMessage() : '');

    check("[{$driver}] project row was persisted by the transaction",
        $result['project'] !== null && Project::query()->where('id', $result['project']->id)->exists());
}

section('8. Rolled-back transaction does not queue a job (database cache)');

DB::statement('TRUNCATE TABLE projects RESTART IDENTITY CASCADE');
DB::table('atlas_chunks')->delete();
DB::table('jobs')->delete();
if (Schema::hasTable('cache_locks')) {
    DB::table('cache_locks')->delete();
}
Cache::flush();
$app['config']->set('cache.default', 'database');
AtlasConfig::refresh();

if (! Schema::hasTable('cache_locks')) {
    echo "  cache_locks table missing — skipping section 8.\n";
} else {
    $threw = null;
    try {
        DB::transaction(function () {
            Project::create([
                'title' => 'Will roll back',
                'body' => 'A body that is long enough to chunk. '.str_repeat('words ', 30),
            ]);
            throw new RuntimeException('forced rollback');
        });
    } catch (RuntimeException $e) {
        $threw = $e;
    }

    check('rollback throw propagated out of DB::transaction',
        $threw instanceof RuntimeException);

    check('project row was rolled back', Project::query()->count() === 0,
        'got '.Project::query()->count().' projects after rollback');

    check('no ChunkContentJob queued for the rolled-back save',
        queuedChunkJobs() === 0, 'got '.queuedChunkJobs().' queued job(s)');
}

// Restore cache store for any later code that might rely on it.
$app['config']->set('cache.default', $originalCacheStore);
AtlasConfig::refresh();

// ─── Summary ────────────────────────────────────────────────────────────────

section('Summary');

if (! empty($failures)) {
    echo "\n  ".count($failures)." assertion(s) FAILED:\n";
    foreach ($failures as $f) {
        echo "    - {$f}\n";
    }
    exit(1);
}

echo "\n  All assertions passed.\n";
