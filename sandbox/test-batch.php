<?php

declare(strict_types=1);

/**
 * Batch API Integration Test (live)
 *
 * Submits real OpenAI + Anthropic batch jobs and verifies submission, status
 * polling, and — when a job finishes within the poll window — result hydration.
 * Batch jobs may take minutes to hours; if a job is still running when the
 * window elapses, the batch id is printed for later verification.
 *
 * Works whether persistence is on (submit() returns a tracked BatchJob) or off
 * (submit() returns a BatchResponse) — polling uses the provider API either way.
 *
 * Usage: php test-batch.php
 *
 * Requires OPENAI_API_KEY and ANTHROPIC_API_KEY in sandbox/.env
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
use Atlasphp\Atlas\Enums\BatchStatus;
use Atlasphp\Atlas\Exceptions\BatchException;
use Atlasphp\Atlas\Persistence\Models\BatchJob;
use Atlasphp\Atlas\Responses\BatchResponse;

function line(string $msg): void
{
    echo $msg.PHP_EOL;
}

/**
 * Get the provider batch id from either submit() return type.
 */
function batchIdOf(BatchResponse|BatchJob $result): string
{
    return $result instanceof BatchResponse ? $result->batchId : (string) $result->batch_id;
}

function pollUntilTerminal(string $provider, string $batchId, int $maxAttempts = 6, int $sleepSeconds = 15): BatchStatus
{
    $response = Atlas::provider($provider)->batchStatus($batchId);

    for ($i = 0; $i < $maxAttempts && ! $response->isTerminal(); $i++) {
        sleep($sleepSeconds);
        $response = Atlas::provider($provider)->batchStatus($batchId);
        line('  poll #'.($i + 1).": {$response->status->value} ({$response->counts->succeeded}/{$response->counts->total})");
    }

    return $response->status;
}

function report(string $provider, string $batchId, BatchStatus $status): void
{
    if ($status === BatchStatus::Completed) {
        $results = iterator_to_array(Atlas::provider($provider)->batchResults($batchId));
        line('  COMPLETED — '.count($results).' results');
        foreach ($results as $r) {
            $detail = match (true) {
                ! $r->succeeded() => 'ERROR '.($r->error?->getMessage() ?? ''),
                isset($r->response->text) => '"'.trim($r->response->text).'"',
                isset($r->response->embeddings) => count($r->response->embeddings[0] ?? []).' dims',
                default => 'ok',
            };
            line("    {$r->customId}: {$r->status->value} → {$detail}");
        }
    } else {
        line("  still {$status->value} — VERIFY LATER: batch id {$batchId}");
    }
}

line('=== Atlas Batch API — live audit (OpenAI + Anthropic) ===');
line('Date: '.date('Y-m-d H:i:s'));
line('');

// ── 1. OpenAI embeddings batch ───────────────────────────────────────────────
line('[1] OpenAI embeddings batch (text-embedding-3-small, 3 inputs)');
$embed = Atlas::batch('openai');
foreach (['the quick brown fox', 'lorem ipsum dolor', 'batch processing is cheaper'] as $i => $text) {
    $embed->add(Atlas::embed('openai', 'text-embedding-3-small')->fromInput($text), key: "embed-{$i}");
}
$embedId = batchIdOf($embed->submit());
line("  submitted: {$embedId}");
$embedStatus = pollUntilTerminal('openai', $embedId);
report('openai', $embedId, $embedStatus);
line('');

// ── 2. OpenAI text batch ─────────────────────────────────────────────────────
line('[2] OpenAI text batch (gpt-4o-mini, 2 prompts)');
$openaiText = Atlas::batch('openai')
    ->add(Atlas::text('openai', 'gpt-4o-mini')->message('Reply with exactly: ALPHA'), key: 'a')
    ->add(Atlas::text('openai', 'gpt-4o-mini')->message('Reply with exactly: BETA'), key: 'b');
$openaiTextId = batchIdOf($openaiText->submit());
line("  submitted: {$openaiTextId}");
$openaiTextStatus = pollUntilTerminal('openai', $openaiTextId);
report('openai', $openaiTextId, $openaiTextStatus);
line('');

// ── 3. Anthropic text batch ──────────────────────────────────────────────────
line('[3] Anthropic text batch (claude-sonnet-4-20250514, 2 prompts)');
$anthropic = Atlas::batch('anthropic')
    ->add(Atlas::text('anthropic', 'claude-sonnet-4-20250514')->message('Reply with exactly: ALPHA'), key: 'a')
    ->add(Atlas::text('anthropic', 'claude-sonnet-4-20250514')->message('Reply with exactly: BETA'), key: 'b');
$anthropicId = batchIdOf($anthropic->submit());
line("  submitted: {$anthropicId}");
$anthropicStatus = pollUntilTerminal('anthropic', $anthropicId);
report('anthropic', $anthropicId, $anthropicStatus);
line('');

// ── 4. Guard rails (no network) ──────────────────────────────────────────────
line('[4] Guard rails');
try {
    Atlas::batch('openai')->add(Atlas::audio('openai', 'whisper-1'), key: 'k');
    line('  FAIL: a non-batchable modality was not rejected');
} catch (BatchException $e) {
    line('  non-batchable modality rejected: OK');
}
try {
    Atlas::batch('openai')->submit();
    line('  FAIL: empty batch was not rejected');
} catch (BatchException $e) {
    line('  empty batch rejected: OK');
}

line('');
line('=== done ===');
line("OpenAI embeddings: {$embedId} ({$embedStatus->value})");
line("OpenAI text:       {$openaiTextId} ({$openaiTextStatus->value})");
line("Anthropic text:    {$anthropicId} ({$anthropicStatus->value})");
