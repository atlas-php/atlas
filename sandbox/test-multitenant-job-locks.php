<?php

declare(strict_types=1);

/**
 * Multi-tenant job-lock isolation — live two-database proof (v3.1.3 fix).
 *
 * Stands up a SECOND real Postgres database and proves the database-scoped
 * `ShouldBeUnique` key fix on live infrastructure:
 *
 *   1. Two tenants whose conversation id=1 lives in separate databases produce
 *      DIFFERENT lock keys → both jobs queue independently (no cross-tenant
 *      suppression). This is the bug the v3.1.3 fix resolves — before it, both
 *      keys were `atlas-queued-1` and the second tenant's job was dropped.
 *   2. Same tenant + same conversation id still dedups to one job (ShouldBeUnique
 *      semantics preserved within a database).
 *
 * Uses the real `database` cache lock (the production mechanism) + `database`
 * queue (so we can count rows in the `jobs` table).
 *
 * Usage:
 *   cd sandbox
 *   php artisan migrate:fresh
 *   php test-multitenant-job-locks.php
 *
 * Requires DB_CONNECTION=pgsql (CREATE DATABASE privilege) and QUEUE_CONNECTION=database.
 */
$app = require __DIR__.'/bootstrap.php';

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Persistence\ProcessQueuedMessage;
use Illuminate\Bus\UniqueLock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$failures = [];

function section(string $title): void
{
    echo "\n".str_repeat('─', 76)."\n  {$title}\n".str_repeat('─', 76)."\n";
}

function check(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    echo '  '.($ok ? '✓' : '✗')." {$name}".($detail !== '' ? " — {$detail}" : '')."\n";
    if (! $ok) {
        $failures[] = $name.($detail !== '' ? ": {$detail}" : '');
    }
}

function queuedProcessJobs(): int
{
    return (int) DB::table('jobs')->where('payload', 'like', '%ProcessQueuedMessage%')->count();
}

function resetJobsAndLocks(): void
{
    DB::table('jobs')->delete();
    if (Schema::hasTable('cache_locks')) {
        DB::table('cache_locks')->delete();
    }
    Cache::flush();
}

// ─── Preflight ──────────────────────────────────────────────────────────────

if (DB::connection()->getDriverName() !== 'pgsql') {
    echo "This test requires PostgreSQL (DB_CONNECTION=pgsql). Aborting.\n";
    exit(1);
}
if (config('queue.default') !== 'database' || ! Schema::hasTable('jobs')) {
    echo "This test requires QUEUE_CONNECTION=database and a migrated `jobs` table (run migrate:fresh).\n";
    exit(1);
}

$app['config']->set('cache.default', 'database');

$queue = app(AtlasConfig::class)->queue;
$centralDb = DB::connection()->getDatabaseName();
$tenantBDb = $centralDb.'_tenant_b';

// ─── Provision a real second tenant database + connection ────────────────────

section('Setup: provision a second real Postgres database');

DB::statement('DROP DATABASE IF EXISTS "'.$tenantBDb.'"');
DB::statement('CREATE DATABASE "'.$tenantBDb.'"');

$base = config('database.connections.'.config('database.default'));
$base['database'] = $tenantBDb;
$app['config']->set('database.connections.tenant_b', $base);

check('central and tenant-b are distinct databases',
    DB::connection('tenant_b')->getDatabaseName() === $tenantBDb && $tenantBDb !== $centralDb,
    "central={$centralDb} tenant_b=".DB::connection('tenant_b')->getDatabaseName());

/**
 * Dispatch a ProcessQueuedMessage as the given tenant (by pointing Atlas
 * persistence at that connection). Forces the PendingDispatch to resolve so
 * the ShouldBeUnique lock is acquired at dispatch time.
 */
function dispatchAs(?string $persistenceConnection, int $conversationId, string $queue): void
{
    config(['atlas.persistence.connection' => $persistenceConnection]);
    AtlasConfig::refresh();

    $pending = ProcessQueuedMessage::dispatch($conversationId, 'test-agent')->onQueue($queue);
    unset($pending); // __destruct → dispatchToQueue → ShouldBeUnique lock check
}

// ─── 1. The keys differ across tenant databases ─────────────────────────────

section('1. Same conversation id in two databases → different lock keys');

config(['atlas.persistence.connection' => null]);
AtlasConfig::refresh();
$keyA = UniqueLock::getKey(new ProcessQueuedMessage(1, 'test-agent'));

config(['atlas.persistence.connection' => 'tenant_b']);
AtlasConfig::refresh();
$keyB = UniqueLock::getKey(new ProcessQueuedMessage(1, 'test-agent'));

check('lock keys differ for conversation id=1 across the two databases',
    $keyA !== $keyB, "A=[{$keyA}] B=[{$keyB}]");

// ─── 2. Cross-tenant: both jobs queue independently (THE FIX) ────────────────

section('2. Two tenants, conversation id=1 each → both jobs queue (no collision)');

resetJobsAndLocks();
dispatchAs(null, 1, $queue);        // tenant A (central) conversation 1
dispatchAs('tenant_b', 1, $queue);  // tenant B conversation 1

check('both tenants\' conversation-1 jobs were queued independently',
    queuedProcessJobs() === 2,
    'queued='.queuedProcessJobs().' (expected 2 — pre-fix this was 1, the second tenant dropped)');

// ─── 3. Same-tenant dedup still works ────────────────────────────────────────

section('3. Same tenant + same conversation id → dedups to one job');

resetJobsAndLocks();
dispatchAs(null, 1, $queue);
dispatchAs(null, 1, $queue);

check('duplicate dispatch within the same database is suppressed by ShouldBeUnique',
    queuedProcessJobs() === 1,
    'queued='.queuedProcessJobs().' (expected 1)');

// ─── Cleanup ─────────────────────────────────────────────────────────────────

config(['atlas.persistence.connection' => null]);
AtlasConfig::refresh();
DB::purge('tenant_b');
DB::statement('DROP DATABASE IF EXISTS "'.$tenantBDb.'"');

// ─── Summary ─────────────────────────────────────────────────────────────────

section('Summary');
if ($failures === []) {
    echo "\n  All assertions passed.\n";
    exit(0);
}
echo "\n  ".count($failures)." assertion(s) FAILED:\n";
foreach ($failures as $f) {
    echo "    - {$f}\n";
}
exit(1);
