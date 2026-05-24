<?php

declare(strict_types=1);

/**
 * Queued Message Dispatch — Transaction Safety End-to-End Test
 *
 * Companion to test-chunked-embeddings-dispatch-on-save.php — verifies the
 * SECOND `ShouldBeUnique` job in atlas (`ProcessQueuedMessage`, dispatched
 * from PersistConversation middleware) handles the same prod scenario
 * correctly across every supported cache driver.
 *
 * The bug pattern is identical to the chunking case:
 *   1. Consumer wraps an `Atlas::agent()` call in their own `DB::transaction()`.
 *   2. Atlas's `PersistConversation` middleware fires inside that transaction.
 *   3. After the inner message-save, it dispatches `ProcessQueuedMessage`.
 *   4. `ShouldBeUnique` acquires a cache lock via `DatabaseLock` (when
 *      `cache.default = database`).
 *   5. The lock INSERT collides → fallback UPDATE → aborts the wrapping
 *      Postgres transaction → SQLSTATE 25P02.
 *
 * The fix in PersistConversation.php wraps the dispatch in
 * `$conversation->getConnection()->afterCommit(...)` so the entire chain
 * (including the lock acquire) runs OUTSIDE the wrapping transaction.
 *
 * This script does NOT invoke a real agent — that would require a live
 * provider, queue worker, and the full agent pipeline. Instead it executes
 * the EXACT dispatch pattern PersistConversation uses (lines 215-230 of
 * src/Persistence/Middleware/PersistConversation.php) so we can prove the
 * pattern is safe under transactions + cache contention without standing
 * up the full stack.
 *
 * Sections:
 *   1. Direct dispatch outside any transaction → fires immediately.
 *   2. Dispatch inside DB::transaction with each cache driver
 *      (database, redis, file, array) → no 25P02, fires after commit.
 *   3. Rolled-back transaction → no job queued.
 *
 * Usage:
 *   cd sandbox
 *   php artisan migrate:fresh
 *   php test-queued-message-dispatch.php
 *
 * Requires:
 *   - DB_CONNECTION=pgsql with pgvector
 *   - QUEUE_CONNECTION=database
 *   - Redis on 127.0.0.1:6379 for the redis-cache test (skipped if unreachable)
 */
$app = require __DIR__.'/bootstrap.php';

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\ProcessQueuedMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

// The Conversation model uses HasAtlasTable, which applies the configured
// table prefix (default `atlas_`). Resolve the actual table name from the
// model to stay correct regardless of the consumer's atlas prefix config.
$conversationsTable = (new Conversation)->getTable();
if (! Schema::hasTable($conversationsTable) || ! Schema::hasTable('jobs')) {
    echo "Missing tables ({$conversationsTable}, jobs) — run `php artisan migrate:fresh` first.\n";
    exit(1);
}

// Configure additional cache stores inline so the script is self-contained
// (sandbox's config/cache.php only ships database + array).
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

function queuedProcessJobs(): int
{
    return (int) DB::table('jobs')
        ->where('payload', 'like', '%ProcessQueuedMessage%')
        ->count();
}

function resetState(): void
{
    $table = (new Conversation)->getTable();
    DB::statement("TRUNCATE TABLE {$table} RESTART IDENTITY CASCADE");
    DB::table('jobs')->delete();
    DB::table('failed_jobs')->delete();
    if (Schema::hasTable('cache_locks')) {
        DB::table('cache_locks')->delete();
    }
    Cache::flush();
}

/**
 * Execute the exact dispatch pattern from PersistConversation.php
 * (lines 215-230). Kept as a separate function so we can call it
 * inside or outside a wrapping transaction.
 */
function dispatchUsingPersistConversationPattern(Conversation $conversation, string $agentKey, string $queue): void
{
    $conversationId = $conversation->id;

    $conversation->getConnection()->afterCommit(static function () use ($conversationId, $agentKey, $queue): void {
        ProcessQueuedMessage::dispatch($conversationId, $agentKey)
            ->onQueue($queue);
    });
}

// ─── 1. Dispatch outside any transaction → fires immediately ────────────────

section('1. Dispatch outside any transaction (immediate fire)');

resetState();
$queue = app(AtlasConfig::class)->queue;

$conversation = Conversation::create([
    'agent' => 'test-agent',
    'title' => 'Outside transaction',
]);

dispatchUsingPersistConversationPattern($conversation, 'test-agent', $queue);

check('one ProcessQueuedMessage queued immediately when no transaction is active',
    queuedProcessJobs() === 1,
    'got '.queuedProcessJobs().' queued job(s)');

// ─── 2. Regression scenario: database cache + lock contention ───────────────
//
// This is the EXACT prod failure mode. A pre-seeded cache_locks row owned
// by a different worker simulates a previous dispatch's lock still being
// held. Before the fix, this would abort the wrapping transaction with
// SQLSTATE 25P02 when PendingDispatch::__destruct tried to acquire the
// already-held lock via DatabaseLock's INSERT/UPDATE pattern.
//
// With the fix, the transaction commits cleanly. ShouldBeUnique then
// correctly suppresses the duplicate dispatch (zero new jobs queued — the
// existing lock holder is doing the work).

section('2. Regression scenario: DB::transaction + database cache + lock contention');

$originalCacheStore = config('cache.default');

if (! Schema::hasTable('cache_locks')) {
    echo "  cache_locks table missing — skipping.\n";
} else {
    resetState();
    $app['config']->set('cache.default', 'database');
    AtlasConfig::refresh();

    $conversation = Conversation::create([
        'agent' => 'test-agent',
        'title' => 'Regression scenario',
    ]);

    // Force lock contention. In prod the lock typically pre-exists because
    // the previous dispatch's lock hasn't expired yet (ShouldBeUnique
    // releases the lock when the job completes, but a recently-completed
    // or in-flight job leaves it set for the rest of its TTL).
    // Derive the lock key from the real job via the framework's own helper so
    // it always matches the job's current uniqueId() (which is database-scoped
    // for multi-tenant safety) — never hardcode the format.
    $prefix = (string) ($app['config']->get('cache.prefix') ?: '');
    $lockKey = $prefix.\Illuminate\Bus\UniqueLock::getKey(
        new ProcessQueuedMessage($conversation->id, 'test-agent')
    );
    DB::table('cache_locks')->insert([
        'key' => $lockKey,
        'owner' => 'sandbox-pre-seed-different-owner',
        'expiration' => time() + 3600,
    ]);

    $threw = null;
    try {
        DB::transaction(function () use ($conversation, $queue) {
            dispatchUsingPersistConversationPattern($conversation, 'test-agent', $queue);
        });
    } catch (Throwable $e) {
        $threw = $e;
    }

    check('transaction completed without 25P02 (the v3.1.1 regression)',
        $threw === null,
        $threw !== null ? get_class($threw).': '.$threw->getMessage() : '');

    check('ShouldBeUnique correctly suppressed the duplicate dispatch',
        queuedProcessJobs() === 0,
        'got '.queuedProcessJobs().' queued job(s) — should be 0 (lock held by simulated prior worker)');
}

// ─── 3. Normal scenario across every cache driver ───────────────────────────
//
// Without lock contention, the dispatch should fire after the transaction
// commits, on every supported cache driver. This proves the afterCommit
// wrap doesn't break the happy path for any consumer config.

$cacheDriversToTest = ['database', 'redis', 'file', 'array'];

foreach ($cacheDriversToTest as $driver) {
    section("3. Normal dispatch inside DB::transaction with cache.default = '{$driver}'");

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

    resetState();
    $app['config']->set('cache.default', $driver);
    AtlasConfig::refresh();

    $conversation = Conversation::create([
        'agent' => 'test-agent',
        'title' => "Cache: {$driver}",
    ]);

    $threw = null;
    try {
        DB::transaction(function () use ($conversation, $queue) {
            dispatchUsingPersistConversationPattern($conversation, 'test-agent', $queue);
        });
    } catch (Throwable $e) {
        $threw = $e;
    }

    check("[{$driver}] transaction completed without throwing",
        $threw === null,
        $threw !== null ? get_class($threw).': '.$threw->getMessage() : '');

    check("[{$driver}] one ProcessQueuedMessage queued after commit",
        queuedProcessJobs() === 1,
        'got '.queuedProcessJobs().' queued job(s)');
}

// ─── 4. Rolled-back transaction → no dispatch ───────────────────────────────

section('4. Rolled-back transaction does not queue a ProcessQueuedMessage');

resetState();
$app['config']->set('cache.default', 'database');
AtlasConfig::refresh();

$conversation = Conversation::create([
    'agent' => 'test-agent',
    'title' => 'Will roll back',
]);

$threw = null;
try {
    DB::transaction(function () use ($conversation, $queue) {
        dispatchUsingPersistConversationPattern($conversation, 'test-agent', $queue);

        throw new RuntimeException('forced rollback');
    });
} catch (RuntimeException $e) {
    $threw = $e;
}

check('rollback throw propagated out of DB::transaction',
    $threw instanceof RuntimeException);

check('no ProcessQueuedMessage queued for the rolled-back transaction',
    queuedProcessJobs() === 0,
    'got '.queuedProcessJobs().' queued job(s)');

// Restore original cache store.
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
