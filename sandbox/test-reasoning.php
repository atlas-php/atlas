<?php

declare(strict_types=1);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\ReasoningEffort;

/**
 * Live audit: request-side reasoning/thinking API.
 *
 * Verifies `->reasoning(effort)` engages each provider's native reasoning and
 * that reasoning text + reasoning-token usage come back.
 *
 * Usage:
 *   php sandbox/test-reasoning.php                 # sweep all providers
 *   php sandbox/test-reasoning.php anthropic        # one provider (default model)
 *   php sandbox/test-reasoning.php openai gpt-5      # explicit model
 */
$app = require __DIR__.'/bootstrap.php';

/** @var array<string, array{model: string, effort: ReasoningEffort, summary: bool}> $matrix */
$matrix = [
    'anthropic' => ['model' => 'claude-sonnet-4-5-20250929', 'effort' => ReasoningEffort::Low, 'summary' => false],
    'openai' => ['model' => 'gpt-5', 'effort' => ReasoningEffort::Low, 'summary' => true],
    'google' => ['model' => 'gemini-2.5-flash', 'effort' => ReasoningEffort::Low, 'summary' => true],
    'xai' => ['model' => 'grok-3-mini', 'effort' => ReasoningEffort::High, 'summary' => false],
];

$only = $argv[1] ?? null;
$modelOverride = $argv[2] ?? null;

$providers = $only !== null ? [$only] : array_keys($matrix);

foreach ($providers as $provider) {
    $cfg = $matrix[$provider] ?? ['model' => $modelOverride, 'effort' => ReasoningEffort::Low, 'summary' => true];
    $model = $modelOverride ?? $cfg['model'];

    echo "\n=== {$provider} / {$model} (effort={$cfg['effort']->value}) ===\n";

    try {
        $response = Atlas::text($provider, $model)
            ->reasoning($cfg['effort'], includeSummary: $cfg['summary'])
            ->message('What is 47 * 89? Think step by step, then give just the number.')
            ->asText();

        echo "Text:           {$response->text}\n";
        echo 'Reasoning:      '.mb_substr($response->reasoning ?? '(null)', 0, 160)."\n";
        echo 'Reasoning toks: '.($response->usage->reasoningTokens ?? '(null)')."\n";
        echo 'Output toks:    '.$response->usage->outputTokens."\n";
        echo 'PASS: '.($response->usage->reasoningTokens > 0 ? 'reasoning tokens reported ✓' : 'no reasoning tokens ✗')."\n";
    } catch (Throwable $e) {
        echo 'ERROR: '.$e->getMessage()."\n";
    }
}

echo "\nDone.\n";
