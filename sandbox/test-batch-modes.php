<?php

declare(strict_types=1);

/**
 * Batch persistence-modes audit (live)
 *
 * Validates both submission modes against the real API for each implemented
 * provider:
 *   - STATELESS (persistence off): submit() returns a BatchResponse; you poll.
 *   - TRACKED   (persistence on):  submit() returns a persisted BatchJob; the
 *                                  atlas:batch-poll command brings results in.
 *
 * Batches resolve later, so this asserts the *mode behavior* and a live status
 * read; result completion is verify-later.
 *
 * Usage: php test-batch-modes.php   (needs OPENAI_API_KEY + ANTHROPIC_API_KEY)
 */
$app = require __DIR__.'/bootstrap.php';

$app['config']->set('atlas.providers', [
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
        'organization' => env('OPENAI_ORGANIZATION'),
    ],
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
    ],
]);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\AtlasManager;
use Atlasphp\Atlas\Pending\BatchRequest;
use Atlasphp\Atlas\Persistence\Models\BatchJob;
use Atlasphp\Atlas\Responses\BatchResponse;
use Illuminate\Support\Facades\Artisan;

function line(string $msg): void
{
    echo $msg.PHP_EOL;
}

/** Flip persistence and rebuild the config/manager singletons + facade root. */
function setPersistence(bool $enabled): void
{
    config(['atlas.persistence.enabled' => $enabled]);
    app()->forgetInstance(AtlasConfig::class);
    app()->forgetInstance(AtlasManager::class);
    Atlas::clearResolvedInstances();
}

/** @return array{0: BatchRequest} */
function batchFor(string $provider): object
{
    if ($provider === 'openai') {
        return Atlas::batch('openai')
            ->add(Atlas::embed('openai', 'text-embedding-3-small')->fromInput('batch modes audit'), key: 'a');
    }

    return Atlas::batch('anthropic')
        ->add(Atlas::text('anthropic', 'claude-sonnet-4-20250514')->message('Say OK'), key: 'a');
}

line('=== Atlas Batch — persistence-modes live audit ===');
line('Date: '.date('Y-m-d H:i:s'));
line('');

$summary = [];

foreach (['openai', 'anthropic'] as $provider) {
    line("── {$provider} ──");

    // STATELESS ----------------------------------------------------------------
    setPersistence(false);
    $stateless = batchFor($provider)->submit();
    $okStateless = $stateless instanceof BatchResponse && $stateless->batchId !== '';
    $statelessStatus = Atlas::provider($provider)->batchStatus($stateless->batchId)->status->value;
    line('  stateless: '.($okStateless ? 'OK' : 'FAIL')
        ." → returned BatchResponse, id={$stateless->batchId}, live status={$statelessStatus}");

    // TRACKED ------------------------------------------------------------------
    setPersistence(true);
    $tracked = batchFor($provider)->submit();
    $isJob = $tracked instanceof BatchJob;
    $persisted = $isJob && BatchJob::query()->where('batch_id', $tracked->batch_id)->exists();
    line('  tracked:   '.($isJob && $persisted ? 'OK' : 'FAIL')
        ." → returned BatchJob #{$tracked->id}, persisted row batch_id={$tracked->batch_id}, status={$tracked->status->value}");

    Artisan::call('atlas:batch-poll', ['--provider' => $provider]);
    line('  poll cmd:  ran → '.trim(Artisan::output()));

    $summary[$provider] = [
        'stateless' => $okStateless,
        'tracked' => $isJob && $persisted,
        'stateless_id' => $stateless->batchId,
        'tracked_id' => $tracked->batch_id,
    ];
    line('');
}

line('=== summary ===');
foreach ($summary as $provider => $r) {
    $verdict = ($r['stateless'] && $r['tracked']) ? 'PASS' : 'FAIL';
    line(sprintf('%-10s %s — stateless %s (%s), tracked %s (%s)',
        $provider, $verdict,
        $r['stateless'] ? '✓' : '✗', $r['stateless_id'],
        $r['tracked'] ? '✓' : '✗', $r['tracked_id'],
    ));
}
