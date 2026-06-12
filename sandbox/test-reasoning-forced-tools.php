<?php

declare(strict_types=1);

/**
 * Repro: Anthropic extended thinking + a forced tool_choice.
 *
 * Anthropic rejects `thinking` combined with a forced tool_choice (type any/tool)
 * with a 400. The regular text/agent path sets `thinking` from ->reasoning() but
 * does NOT drop a forced tool_choice (only the structured() path does). Expect a
 * 400 BEFORE the fix; a clean tool-loop completion AFTER.
 *
 * Usage: php sandbox/test-reasoning-forced-tools.php
 */
$app = require __DIR__.'/bootstrap.php';

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\ReasoningEffort;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Tools\Tool;
use Atlasphp\Atlas\Tools\ToolChoice;

$model = $argv[1] ?? 'claude-sonnet-4-5-20250929';

$weather = new class extends Tool
{
    public function name(): string
    {
        return 'get_weather';
    }

    public function description(): string
    {
        return 'Get the current weather for a city. Returns a short description.';
    }

    public function parameters(): array
    {
        return [Schema::string('city', 'City name')];
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $context
     */
    public function handle(array $args, array $context): mixed
    {
        return 'Sunny, 24C in '.($args['city'] ?? 'unknown');
    }
};

/**
 * @param  callable():TextResponse  $run
 */
function attempt(string $label, callable $run): void
{
    echo "\n=== {$label} ===\n";

    try {
        $response = $run();
        echo 'OK — steps: '.count($response->steps).', text: '.substr((string) $response->text, 0, 80)."\n";
        echo "RESULT: PASS (no 400)\n";
    } catch (Throwable $e) {
        echo 'ERROR ('.get_class($e).'): '.$e->getMessage()."\n";
        echo "RESULT: FAIL (provider rejected)\n";
    }
}

// 1) reasoning + forceTools() (tool_choice = required/any)
attempt("anthropic / {$model} — reasoning + forceTools()", function () use ($model, $weather) {
    return Atlas::text('anthropic', $model)
        ->reasoning(ReasoningEffort::Low)
        ->withTools([$weather])
        ->forceTools()
        ->withMaxSteps(4)
        ->message('What is the weather in Paris? Use the tool.')
        ->asText();
});

// 2) reasoning + a specific named tool choice (tool_choice = tool)
attempt("anthropic / {$model} — reasoning + toolChoice(tool('get_weather'))", function () use ($model, $weather) {
    return Atlas::text('anthropic', $model)
        ->reasoning(ReasoningEffort::Low)
        ->withTools([$weather])
        ->toolChoice(ToolChoice::tool('get_weather'))
        ->withMaxSteps(4)
        ->message('What is the weather in Tokyo? Use the tool.')
        ->asText();
});

// 3) Control: reasoning + tools with default (auto) choice — should already work
attempt("anthropic / {$model} — reasoning + tools (auto choice, control)", function () use ($model, $weather) {
    return Atlas::text('anthropic', $model)
        ->reasoning(ReasoningEffort::Low)
        ->withTools([$weather])
        ->withMaxSteps(4)
        ->message('What is the weather in Berlin? Use the tool.')
        ->asText();
});

echo "\nDone.\n";
