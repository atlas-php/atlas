<?php

declare(strict_types=1);

use App\Models\User;
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\ReasoningEffort;
use Atlasphp\Atlas\Persistence\Models\ExecutionStep;

/**
 * Live audit: reasoning is WORKING, TRACKED, and RECORDED across providers.
 *
 * For each provider it runs a persisted agent with reasoning + a multi-step tool
 * loop, then inspects the recorded execution steps to confirm:
 *   - working:   the loop completed with the right answer (60271)
 *   - tracked:   reasoning text was recorded on the steps
 *   - recorded:  signed reasoning blocks (Anthropic signature / OpenAI
 *                encrypted_content) were persisted for replay
 *
 * Usage:
 *   php sandbox/test-reasoning-recording.php
 *   php sandbox/test-reasoning-recording.php anthropic
 */
$app = require __DIR__.'/bootstrap.php';

$user = User::firstOrFail();

/** @var array<string, string> $matrix */
$matrix = [
    'anthropic' => 'claude-sonnet-4-5-20250929',
    'openai' => 'gpt-5',
    'google' => 'gemini-2.5-flash',
    'xai' => 'grok-3-mini',
];

$only = $argv[1] ?? null;
if ($only !== null) {
    $matrix = [$only => $argv[2] ?? $matrix[$only] ?? ''];
}

foreach ($matrix as $provider => $model) {
    echo "\n══════════════════════════════════════════════════════════\n";
    echo "  {$provider} / {$model}\n";
    echo "══════════════════════════════════════════════════════════\n";

    try {
        $response = Atlas::agent('reasoning-audit')
            ->for($user)
            ->withProvider($provider, $model)
            ->reasoning(ReasoningEffort::Low)
            ->withMaxSteps(6)
            ->message('Compute 47 x 89 and 123 x 456 using the multiply tool (one call per product), then add the two products and give the final sum.')
            ->asText();

        $answer = str_replace(',', '', $response->text);
        $working = str_contains($answer, '60271');
        echo 'WORKING:  '.($working ? '✓' : '✗ (expected 60271)').' — "'.mb_substr(trim($response->text), 0, 80)."\"\n";

        $executionId = $response->meta['execution_id'] ?? null;
        if ($executionId === null) {
            echo "TRACKED:  ✗ no execution recorded\n";

            continue;
        }

        $steps = ExecutionStep::where('execution_id', $executionId)->orderBy('sequence')->get();

        $reasoningTextSteps = 0;
        $signedBlockSteps = 0;
        $blockKinds = [];

        foreach ($steps as $step) {
            if (! empty($step->reasoning)) {
                $reasoningTextSteps++;
            }

            $blocks = $step->metadata['reasoning_blocks'] ?? [];
            foreach ($blocks as $block) {
                $type = $block['type'] ?? '?';
                $signed = ! empty($block['signature']) || ! empty($block['encrypted_content']);
                $blockKinds[] = $type.($signed ? '(signed)' : '');
                if ($signed) {
                    $signedBlockSteps++;
                }
            }
        }

        echo "TRACKED:  {$steps->count()} steps recorded; {$reasoningTextSteps} carry reasoning text\n";
        echo 'RECORDED: '.($signedBlockSteps > 0
            ? "{$signedBlockSteps} step(s) with signed reasoning blocks ✓ [".implode(', ', array_unique($blockKinds)).']'
            : 'no signed reasoning blocks (provider carries signatures elsewhere — e.g. Google per-tool thoughtSignature)')."\n";
    } catch (Throwable $e) {
        echo 'ERROR ('.get_class($e).'): '.$e->getMessage()."\n";
    }
}

echo "\nDone.\n";
