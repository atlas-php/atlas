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
