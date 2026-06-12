<?php

declare(strict_types=1);

/**
 * Google (Gemini) batch — full end-to-end audit, both persistence modes (live).
 *
 *   STATELESS: submit → poll provider → fetch results → verify text.
 *   TRACKED:   submit (persisted) → atlas:batch-poll → verify batch_jobs +
 *              batch_results rows are accurate.
 *
 * Usage: php test-batch-google-e2e.php   (needs GEMINI_API_KEY)
 */
$app = require __DIR__.'/bootstrap.php';

$app['config']->set('atlas.providers', [
    'google' => [
        'api_key' => env('GEMINI_API_KEY'),
        'url' => env('GOOGLE_URL', 'https://generativelanguage.googleapis.com'),
    ],
]);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\AtlasManager;
use Atlasphp\Atlas\Enums\BatchStatus;
use Atlasphp\Atlas\Persistence\Models\BatchJob;
use Atlasphp\Atlas\Persistence\Models\BatchResult;
use Atlasphp\Atlas\Responses\BatchResponse;
use Illuminate\Support\Facades\Artisan;

function line(string $m): void
{
    echo $m.PHP_EOL;
}
$pass = true;
function check(string $label, bool $ok): void
{
    global $pass;
    $pass = $pass && $ok;
    line('  ['.($ok ? 'PASS' : 'FAIL')."] {$label}");
}
function setPersistence(bool $on): void
{
    config(['atlas.persistence.enabled' => $on]);
    app()->forgetInstance(AtlasConfig::class);
    app()->forgetInstance(AtlasManager::class);
    Atlas::clearResolvedInstances();
}
function googleBatch(): object
{
    return Atlas::batch('google')
        ->add(Atlas::text('google', 'gemini-2.5-flash')->message('Reply with exactly: ALPHA'), key: 'request-1')
        ->add(Atlas::text('google', 'gemini-2.5-flash')->message('Reply with exactly: BETA'), key: 'request-2');
}

line('=== Google (Gemini) batch — end-to-end, both modes ===');
line('Date: '.date('Y-m-d H:i:s'));
line('');

// ── STATELESS ────────────────────────────────────────────────────────────────
line('[STATELESS] (persistence off)');
setPersistence(false);
$resp = googleBatch()->submit();
check('submit returns a BatchResponse', $resp instanceof BatchResponse);
check('non-empty batch id', $resp->batchId !== '');
line("  batch id: {$resp->batchId}");

$status = $resp->status;
for ($i = 0; $i < 14 && ! $status->isTerminal(); $i++) {
    sleep(15);
    $status = Atlas::provider('google')->batchStatus($resp->batchId)->status;
    line('  poll #'.($i + 1).": {$status->value}");
}
check('reached completed', $status === BatchStatus::Completed);

if ($status === BatchStatus::Completed) {
    $results = [];
    foreach (Atlas::provider('google')->batchResults($resp->batchId) as $r) {
        $results[$r->customId] = $r->response !== null ? trim($r->response->text) : 'ERR';
        line("    {$r->customId}: {$r->status->value} → \"{$results[$r->customId]}\"");
    }
    check('2 results returned', count($results) === 2);
    check('request-1 → ALPHA', ($results['request-1'] ?? '') === 'ALPHA');
    check('request-2 → BETA', ($results['request-2'] ?? '') === 'BETA');
}
line('');

// ── TRACKED ──────────────────────────────────────────────────────────────────
line('[TRACKED] (persistence on)');
setPersistence(true);
BatchResult::query()->delete();
BatchJob::query()->delete();

$job = googleBatch()->submit();
check('submit returns a persisted BatchJob', $job instanceof BatchJob);
check('job row persisted with batch id', $job instanceof BatchJob && BatchJob::query()->where('batch_id', $job->batch_id)->exists());
line("  job #{$job->id} batch_id={$job->batch_id} status={$job->status->value}");

for ($i = 0; $i < 14; $i++) {
    Artisan::call('atlas:batch-poll', ['--provider' => 'google']);
    $job->refresh();
    if ($job->status->isTerminal()) {
        break;
    }
    sleep(15);
}
line("  final job status: {$job->status->value}");

if ($job->status === BatchStatus::Completed) {
    line('  atlas_batch_jobs: status='.$job->status->value." total={$job->total} succeeded={$job->succeeded} usage=".json_encode($job->usage));
    check('job completed via poll command', $job->status === BatchStatus::Completed);
    check('counts accurate (2/2)', $job->total === 2 && $job->succeeded === 2);
    check('usage rolled up', (int) ($job->usage['input_tokens'] ?? 0) > 0);

    $rows = BatchResult::query()->where('batch_job_id', $job->id)->orderBy('custom_id')->get();
    foreach ($rows as $r) {
        line("  atlas_batch_results: {$r->custom_id} {$r->status->value} ".json_encode($r->response));
    }
    check('2 result rows (nothing missing)', $rows->count() === 2);
    check('custom_ids correlate', $rows->pluck('custom_id')->all() === ['request-1', 'request-2']);
    check('request-1 text = ALPHA', ($rows->firstWhere('custom_id', 'request-1')->response['text'] ?? '') === 'ALPHA');
    check('request-2 text = BETA', ($rows->firstWhere('custom_id', 'request-2')->response['text'] ?? '') === 'BETA');
}

line('');
line('=== '.($pass ? 'GOOGLE E2E PASSED (both modes)' : 'GOOGLE E2E FAILED').' ===');
exit($pass ? 0 : 1);
