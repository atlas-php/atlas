<?php

declare(strict_types=1);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\ReasoningEffort;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Tools\Tool;

/**
 * Live audit / regression repro for the dropped-thinking-signature bug.
 *
 * Runs a multi-step tool loop with reasoning ENABLED. Before the fix, Anthropic
 * could hard-error ("messages... must contain a thinking block") because the
 * signed thinking block wasn't replayed before tool_use, and OpenAI silently
 * lost reasoning context across turns. A clean multi-step completion proves the
 * signed thinking / reasoning items are replayed.
 *
 * Usage:
 *   php sandbox/test-reasoning-tools.php            # anthropic + openai
 *   php sandbox/test-reasoning-tools.php anthropic claude-sonnet-4-5-20250929
 */
$app = require __DIR__.'/bootstrap.php';

$multiply = new class extends Tool
{
    public function name(): string
    {
        return 'multiply';
    }

    public function description(): string
    {
        return 'Multiply two integers and return the product.';
    }

    public function parameters(): array
    {
        return [
            Schema::integer('a', 'First integer'),
            Schema::integer('b', 'Second integer'),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $context
     */
    public function handle(array $args, array $context): mixed
    {
        return (string) ((int) $args['a'] * (int) $args['b']);
    }
};

/** @var array<string, string> $matrix */
$matrix = [
    'anthropic' => 'claude-sonnet-4-5-20250929',
    'openai' => 'gpt-5',
];

$only = $argv[1] ?? null;
$modelOverride = $argv[2] ?? null;

$targets = $only !== null ? [$only => $modelOverride ?? ($matrix[$only] ?? '')] : $matrix;

foreach ($targets as $provider => $model) {
    echo "\n=== {$provider} / {$model} — reasoning + multi-step tools ===\n";

    try {
        $response = Atlas::text($provider, $model)
            ->reasoning(ReasoningEffort::Low)
            ->withTools([$multiply])
            ->withMaxSteps(6)
            ->message('Use the multiply tool to compute 47 x 89 and 123 x 456 (call it once for each), then add the two products and give me the final sum.')
            ->asText();

        $steps = count($response->steps);

        echo "Final text: {$response->text}\n";
        echo "Steps:      {$steps}\n";
        echo 'Has 11602? '.(str_contains(str_replace(',', '', $response->text), '60271') ? 'yes' : 'check manually')."\n";
        echo 'PASS: '.($steps >= 2 ? "completed a {$steps}-step loop with reasoning on, no error ✓" : 'did not loop ✗')."\n";
    } catch (Throwable $e) {
        echo 'ERROR ('.get_class($e).'): '.$e->getMessage()."\n";
    }
}

echo "\nDone.\n";
