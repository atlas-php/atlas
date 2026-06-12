<?php

declare(strict_types=1);

/**
 * Batch persistence-tables audit (live)
 *
 * Submits a tracked batch in a group, polls it to completion via the real
 * atlas:batch-poll command, then dumps and validates all three tables
 * (atlas_batch_groups, atlas_batch_jobs, atlas_batch_results) for accuracy and
 * completeness. Finally validates that atlas:batch-prune removes aged data.
 *
 * Usage: php test-batch-tables.php   (needs OPENAI_API_KEY; persistence enabled)
 */
$app = require __DIR__.'/bootstrap.php';

$app['config']->set('atlas.providers', [
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
        'organization' => env('OPENAI_ORGANIZATION'),
    ],
]);
$app['config']->set('atlas.persistence.enabled', true);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Persistence\Models\BatchGroup;
use Atlasphp\Atlas\Persistence\Models\BatchJob;
use Atlasphp\Atlas\Persistence\Models\BatchResult;
use Illuminate\Support\Facades\Artisan;

function line(string $msg): void
{
    echo $msg.PHP_EOL;
}

$pass = true;
function check(string $label, bool $ok): void
{
    global $pass;
    $pass = $pass && $ok;
    line('  ['.($ok ? 'PASS' : 'FAIL')."] {$label}");
}

// Clean slate so the dump is unambiguous.
BatchResult::query()->delete();
BatchJob::query()->delete();
BatchGroup::query()->delete();

line('=== Batch tables audit (live OpenAI) ===');
line('Date: '.date('Y-m-d H:i:s'));
line('');

// ── Submit a tracked batch inside a group ────────────────────────────────────
$group = Atlas::batchGroup('table-validation');
$job = Atlas::batch('openai')->group($group)
    ->add(Atlas::text('openai', 'gpt-4o-mini')->message('Reply with exactly: ALPHA'), key: 'rec-1')
    ->add(Atlas::text('openai', 'gpt-4o-mini')->message('Reply with exactly: BETA'), key: 'rec-2')
    ->submit();

line("Submitted: job #{$job->id} (batch_id={$job->batch_id}) in group #{$group->id}");

// ── Poll to completion via the real command ──────────────────────────────────
for ($i = 0; $i < 12; $i++) {
    Artisan::call('atlas:batch-poll');
    $job->refresh();
    if ($job->status->isTerminal()) {
        break;
    }
    sleep(15);
}
line("Final job status: {$job->status->value} (after polling)");
line('');

if (! $job->status->isSuccessful()) {
    line('Job did not complete within the window — re-run to validate tables. Batch id: '.$job->batch_id);
    exit(0);
}

// ── atlas_batch_jobs ─────────────────────────────────────────────────────────
line('atlas_batch_jobs:');
line("  provider={$job->provider} modality={$job->modality} status={$job->status->value}");
line("  counts: total={$job->total} succeeded={$job->succeeded} failed={$job->failed} processing={$job->processing}");
line('  usage: '.json_encode($job->usage));
line("  submitted_at={$job->submitted_at} completed_at={$job->completed_at}");
check('provider/modality recorded', $job->provider === 'openai' && $job->modality === 'text');
check('status completed', $job->status->value === 'completed');
check('counts accurate (2 total / 2 succeeded)', $job->total === 2 && $job->succeeded === 2 && $job->failed === 0);
check('usage rolled up (input+output > 0)', (int) ($job->usage['input_tokens'] ?? 0) > 0 && (int) ($job->usage['output_tokens'] ?? 0) > 0);
check('timestamps set', $job->submitted_at !== null && $job->completed_at !== null);
line('');

// ── atlas_batch_results ──────────────────────────────────────────────────────
line('atlas_batch_results:');
$results = BatchResult::query()->where('batch_job_id', $job->id)->orderBy('custom_id')->get();
foreach ($results as $r) {
    line("  custom_id={$r->custom_id} status={$r->status->value} response=".json_encode($r->response).' usage='.json_encode($r->usage));
}
$keys = $results->pluck('custom_id')->all();
check('one row per submitted line (nothing missing)', $results->count() === 2);
check('custom_ids match the submitted keys', $keys === ['rec-1', 'rec-2']);
check('responses carry text + finish_reason', isset($results[0]->response['text'], $results[0]->response['finish_reason']));
check('per-line usage recorded', (int) ($results[0]->usage['input_tokens'] ?? 0) > 0);
check('job.succeeded equals succeeded result rows', $job->succeeded === $results->where('status.value', 'succeeded')->count() || $job->succeeded === $results->filter(fn ($r) => $r->status->value === 'succeeded')->count());
$rolled = array_sum($results->map(fn ($r) => (int) ($r->usage['input_tokens'] ?? 0))->all());
check('rolled-up input tokens == sum of line input tokens', (int) ($job->usage['input_tokens'] ?? 0) === $rolled);
line('');

// ── atlas_batch_groups ───────────────────────────────────────────────────────
line('atlas_batch_groups:');
$progress = $group->progress();
line("  label={$group->label} progress=".json_encode($progress).' isComplete='.($group->isComplete() ? 'true' : 'false'));
check('group aggregates its jobs', $progress['total'] === 2 && $progress['succeeded'] === 2 && $progress['jobs'] === 1);
check('group isComplete', $group->isComplete() === true);
line('');

// ── Prune ────────────────────────────────────────────────────────────────────
line('Prune (backdate job + group 120 days, retention 90):');
$job->forceFill(['created_at' => now()->subDays(120)])->save();
$group->forceFill(['created_at' => now()->subDays(120)])->save();
Artisan::call('atlas:batch-prune');
line('  '.trim(Artisan::output()));
check('job deleted', BatchJob::query()->where('id', $job->id)->doesntExist());
check('results cascade-deleted', BatchResult::query()->where('batch_job_id', $job->id)->doesntExist());
check('empty aged group deleted', BatchGroup::query()->where('id', $group->id)->doesntExist());
line('');

line('=== '.($pass ? 'ALL CHECKS PASSED' : 'SOME CHECKS FAILED').' ===');
exit($pass ? 0 : 1);
