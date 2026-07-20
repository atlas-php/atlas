<?php

declare(strict_types=1);

/**
 * Google (Gemini) batch — live verification.
 *
 * Submits a small inline text batch, polls to completion, and parses results.
 * Verifies the real Gemini batchGenerateContent contract end-to-end.
 *
 * Usage: php test-batch-google.php   (needs GEMINI_API_KEY)
 */
$app = require __DIR__.'/bootstrap.php';

$app['config']->set('atlas.providers', [
    'google' => [
        'api_key' => env('GEMINI_API_KEY'),
        'url' => env('GOOGLE_URL', 'https://generativelanguage.googleapis.com'),
    ],
]);
$app['config']->set('atlas.persistence.enabled', false); // stateless: exercise the provider API directly
// The Atlas singletons + facade root are resolved in bootstrap before the toggle
// above, so drop them to force a stateless rebuild — otherwise submit() runs in
// tracked mode and returns a BatchJob whose camelCase batchId is null.
$app->forgetInstance(\Atlasphp\Atlas\AtlasConfig::class);
$app->forgetInstance(\Atlasphp\Atlas\AtlasManager::class);
\Atlasphp\Atlas\Atlas::clearResolvedInstances();

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\BatchStatus;

function line(string $msg): void
{
    echo $msg.PHP_EOL;
}

line('=== Google (Gemini) batch — live ===');
line('Date: '.date('Y-m-d H:i:s'));

$response = Atlas::batch('google')
    ->add(Atlas::text('google', 'gemini-2.5-flash')->message('Reply with exactly: ALPHA'), key: 'request-1')
    ->add(Atlas::text('google', 'gemini-2.5-flash')->message('Reply with exactly: BETA'), key: 'request-2')
    ->submit();

line("submitted: {$response->batchId} — status {$response->status->value}");

$status = $response->status;
for ($i = 0; $i < 10 && ! $status->isTerminal(); $i++) {
    sleep(15);
    $r = Atlas::provider('google')->batchStatus($response->batchId);
    $status = $r->status;
    line('  poll #'.($i + 1).": {$status->value} ({$r->counts->succeeded}/{$r->counts->total})");
}

if ($status === BatchStatus::Completed) {
    line('COMPLETED — results:');
    foreach (Atlas::provider('google')->batchResults($response->batchId) as $result) {
        $text = $result->response !== null ? trim($result->response->text) : ('ERR '.($result->error?->getMessage() ?? ''));
        line("  {$result->customId}: {$result->status->value} → \"{$text}\"");
    }
} else {
    line("still {$status->value} — VERIFY LATER: {$response->batchId}");
}
