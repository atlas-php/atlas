<?php

declare(strict_types=1);

/**
 * Batch demo (live) — populates the tables and LEAVES the data in place.
 *
 * Submits a tracked OpenAI text batch in a group, polls it to completion via
 * atlas:batch-poll, then prints the rows. Unlike the audit scripts, it does NOT
 * clean up or prune — so you can inspect atlas_batch_jobs / atlas_batch_results
 * / atlas_batch_groups afterwards.
 *
 * Usage: php test-batch-demo.php   (needs OPENAI_API_KEY; persistence enabled)
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
use Atlasphp\Atlas\Persistence\Models\BatchResult;
use Illuminate\Support\Facades\Artisan;

function line(string $msg): void
{
    echo $msg.PHP_EOL;
}

line('=== Batch demo — leaves data in the tables ===');
line('Date: '.date('Y-m-d H:i:s'));

$group = Atlas::batchGroup('demo-run');
$job = Atlas::batch('openai')->group($group)
    ->add(Atlas::text('openai', 'gpt-4o-mini')->message('Reply with exactly: ALPHA'), key: 'rec-1')
    ->add(Atlas::text('openai', 'gpt-4o-mini')->message('Reply with exactly: BETA'), key: 'rec-2')
    ->submit();

line("Submitted job #{$job->id} (batch_id={$job->batch_id}) in group #{$group->id} — polling…");

for ($i = 0; $i < 16; $i++) {
    Artisan::call('atlas:batch-poll');
    $job->refresh();
    line('  poll: '.$job->status->value." ({$job->succeeded}/{$job->total})");
    if ($job->status->isTerminal()) {
        break;
    }
    sleep(15);
}
line('');

line('atlas_batch_groups:');
line("  #{$group->id} label={$group->label} progress=".json_encode($group->progress()).' complete='.($group->isComplete() ? 'yes' : 'no'));
line('');

line('atlas_batch_jobs:');
line("  #{$job->id} provider={$job->provider} modality={$job->modality} status={$job->status->value} counts={$job->succeeded}/{$job->total} usage=".json_encode($job->usage));
line('');

line('atlas_batch_results:');
foreach (BatchResult::query()->where('batch_job_id', $job->id)->orderBy('custom_id')->get() as $r) {
    line("  custom_id={$r->custom_id} status={$r->status->value} response=".json_encode($r->response).' usage='.json_encode($r->usage));
}
line('');
line('Data left in place. Inspect with:');
line("  sqlite3 database/database.sqlite 'SELECT * FROM atlas_batch_results;'");
