<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Responses\StreamChunk;

/**
 * Streaming integration test for all providers.
 *
 * Tests real-time text delta streaming, chunk accumulation, usage
 * tracking, and tool call chunk visibility across providers.
 *
 * Usage: php sandbox/test-streaming.php [openai|anthropic|google|xai|all]
 *
 * Requires API keys in sandbox/.env
 */
$app = require __DIR__.'/bootstrap.php';

$target = $argv[1] ?? 'all';

$providers = match ($target) {
    'openai' => [['openai', 'gpt-4o-mini']],
    'anthropic' => [['anthropic', 'claude-sonnet-4-5-20250929']],
    'google' => [['google', 'gemini-2.5-flash']],
    'xai' => [['xai', 'grok-3-mini-fast']],
    'all' => [
        ['openai', 'gpt-4o-mini'],
        ['anthropic', 'claude-sonnet-4-5-20250514'],
        ['google', 'gemini-2.5-flash'],
        ['xai', 'grok-3-mini-fast'],
    ],
    default => exit("Unknown provider: {$target}\nUsage: php sandbox/test-streaming.php [openai|anthropic|google|xai|all]\n"),
};

echo "=== Atlas Streaming Test ===\n\n";

$passed = 0;
$failed = 0;

foreach ($providers as [$provider, $model]) {
    echo "─── {$provider} ({$model}) ───\n\n";

    // ── Test 1: Basic text streaming ────────────────────────
    echo "  1. Text streaming...\n";

    try {
        $stream = Atlas::text($provider, $model)
            ->message('Say exactly: "Hello streaming world"')
            ->withTemperature(0.0)
            ->asStream();

        $textChunks = 0;
        $doneChunks = 0;
        $chunkTypes = [];

        foreach ($stream as $chunk) {
            $chunkTypes[] = $chunk->type->value;

            if ($chunk->type === ChunkType::Text) {
                $textChunks++;
            }

            if ($chunk->type === ChunkType::Done) {
                $doneChunks++;
            }
        }

        $text = $stream->getText();
        $usage = $stream->getUsage();
        $finishReason = $stream->getFinishReason();

        $textOk = $text !== '' && str_contains(strtolower($text), 'hello');
        $doneOk = $doneChunks === 1;
        $usageOk = $usage !== null && $usage->inputTokens > 0;
        $finishOk = $finishReason !== null;

        echo "     Text chunks: {$textChunks} | Done: {$doneChunks}\n";
        echo '     Text: '.substr($text, 0, 80)."\n";
        echo '     Usage: '.($usage ? "in={$usage->inputTokens} out={$usage->outputTokens}" : 'null')."\n";
        echo '     Finish: '.($finishReason?->value ?? 'null')."\n";

        if ($textOk && $doneOk && $usageOk && $finishOk) {
            echo "     PASS\n\n";
            $passed++;
        } else {
            echo '     FAIL:'.($textOk ? '' : ' no-text').($doneOk ? '' : ' no-done').($usageOk ? '' : ' no-usage').($finishOk ? '' : ' no-finish')."\n\n";
            $failed++;
        }
    } catch (Throwable $e) {
        echo "     ERROR: {$e->getMessage()}\n\n";
        $failed++;
    }

    // ── Test 2: Callback fires ──────────────────────────────
    echo "  2. onChunk callback...\n";

    try {
        $callbackCount = 0;

        $stream = Atlas::text($provider, $model)
            ->message('Say "test"')
            ->withTemperature(0.0)
            ->asStream();

        $stream->onChunk(function (StreamChunk $chunk) use (&$callbackCount) {
            $callbackCount++;
        });

        iterator_to_array($stream);

        if ($callbackCount > 0) {
            echo "     Callbacks fired: {$callbackCount} — PASS\n\n";
            $passed++;
        } else {
            echo "     FAIL: No callbacks fired\n\n";
            $failed++;
        }
    } catch (Throwable $e) {
        echo "     ERROR: {$e->getMessage()}\n\n";
        $failed++;
    }

    // ── Test 3: then() callback ─────────────────────────────
    echo "  3. then() callback (chainable)...\n";

    try {
        $thenCalled = 0;

        $stream = Atlas::text($provider, $model)
            ->message('Say "done"')
            ->withTemperature(0.0)
            ->asStream();

        $stream->then(function () use (&$thenCalled) {
            $thenCalled++;
        });

        // Add a second then() to verify chaining works
        $stream->then(function () use (&$thenCalled) {
            $thenCalled++;
        });

        iterator_to_array($stream);

        if ($thenCalled === 2) {
            echo "     Both then() callbacks fired — PASS\n\n";
            $passed++;
        } else {
            echo "     FAIL: Expected 2 callbacks, got {$thenCalled}\n\n";
            $failed++;
        }
    } catch (Throwable $e) {
        echo "     ERROR: {$e->getMessage()}\n\n";
        $failed++;
    }
}

echo "=== Summary ===\n";
echo "Passed: {$passed} | Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
