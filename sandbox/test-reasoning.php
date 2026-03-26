<?php

declare(strict_types=1);

use App\Models\User;
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Persistence\Models\ExecutionStep;

$app = require __DIR__.'/bootstrap.php';

$user = User::findOrFail(1);

$provider = $argv[1] ?? 'xai';
$model = $argv[2] ?? 'grok-3-mini';

echo "=== Testing reasoning with {$provider}/{$model} ===\n\n";

$response = Atlas::agent('assistant')
    ->for($user)
    ->withProvider($provider, $model)
    ->message('What is 47 * 89? Think step by step.')
    ->asText();

echo "Text: {$response->text}\n";
echo 'Reasoning: '.($response->reasoning ?? '(null)')."\n\n";

echo "--- Steps ---\n";
$executionId = $response->meta['execution_id'] ?? null;

if ($executionId) {
    ExecutionStep::where('execution_id', $executionId)
        ->orderBy('sequence')
        ->get()
        ->each(function ($s) {
            echo "  [{$s->id}] seq={$s->sequence} | {$s->finish_reason}\n";
            echo '    content: '.mb_substr($s->content ?? '(null)', 0, 100)."\n";
            echo '    reasoning: '.mb_substr($s->reasoning ?? '(null)', 0, 100)."\n";
            echo "    status: {$s->status->label()}\n\n";
        });
}
