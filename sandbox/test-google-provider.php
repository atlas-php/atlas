<?php

declare(strict_types=1);

/**
 * Google Gemini Provider Integration Test
 *
 * Validates all Google Gemini provider modalities, usage tracking, response accuracy,
 * and provider tool support against the real API.
 *
 * Usage: php test-google-provider.php
 *
 * Requires GOOGLE_API_KEY in sandbox/.env
 */
$app = require __DIR__.'/bootstrap.php';

// Ensure provider config from env
$app['config']->set('atlas.default', ['provider' => 'google', 'model' => 'gemini-2.5-flash']);
$app['config']->set('atlas.providers', [
    'google' => [
        'api_key' => env('GEMINI_API_KEY', env('GOOGLE_API_KEY')),
        'url' => env('GOOGLE_URL', 'https://generativelanguage.googleapis.com'),
    ],
]);

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Messages\ToolResultMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Schema\Schema;

// ─── Test Runner ─────────────────────────────────────────────────────────────

$passed = 0;
$failed = 0;
$skipped = 0;
$errors = [];

function test(string $name, Closure $fn): void
{
    global $passed, $failed, $errors;

    echo "\n  {$name} ";

    try {
        $fn();
        echo '✓';
        $passed++;
    } catch (Throwable $e) {
        echo '✗ FAIL';
        $msg = get_class($e).': '.$e->getMessage();
        $errors[] = "  {$name}: {$msg}";
        $failed++;
    }
}

function skip(string $name, string $reason): void
{
    global $skipped;

    echo "\n  {$name} ⊘ SKIP ({$reason})";
    $skipped++;
}

function assert_true(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
}

// ─── Tests ───────────────────────────────────────────────────────────────────

echo '╔══════════════════════════════════════════════╗';
echo "\n║   Google Gemini Provider Integration Tests   ║";
echo "\n╚══════════════════════════════════════════════╝";

// ── Models Endpoint ──────────────────────────────────────────────────────────

echo "\n\n── Models Endpoint";

test('models list returns known models', function () {
    $models = Atlas::provider(Provider::Google)->models();

    assert_true(count($models->models) > 5, 'Should have many models, got: '.count($models->models));
    assert_true(in_array('gemini-2.5-flash', $models->models, true), 'Should include gemini-2.5-flash');
    assert_true(in_array('gemini-embedding-001', $models->models, true), 'Should include gemini-embedding-001');

    // Verify sorted
    $sorted = $models->models;
    sort($sorted);
    assert_true($models->models === $sorted, 'Models should be sorted alphabetically');

    // Verify models/ prefix is stripped
    foreach ($models->models as $model) {
        assert_true(! str_starts_with($model, 'models/'), "Model should not have models/ prefix: {$model}");
    }
});

test('validate returns true', function () {
    $valid = Atlas::provider(Provider::Google)->validate();

    assert_true($valid === true, 'validate() should return true');
});

test('capabilities are accurate', function () {
    $cap = Atlas::provider(Provider::Google)->capabilities();

    assert_true($cap->supports('text'), 'Should support text');
    assert_true($cap->supports('stream'), 'Should support stream');
    assert_true($cap->supports('structured'), 'Should support structured');
    assert_true($cap->supports('image'), 'Should support image');
    assert_true(! $cap->supports('imageToText'), 'Should NOT support imageToText');
    assert_true(! $cap->supports('audio'), 'Should NOT support audio');
    assert_true(! $cap->supports('audioToText'), 'Should NOT support audioToText');
    assert_true(! $cap->supports('video'), 'Should NOT support video');
    assert_true($cap->supports('embed'), 'Should support embed');
    assert_true(! $cap->supports('moderate'), 'Should NOT support moderate');
    assert_true($cap->supports('vision'), 'Should support vision');
    assert_true($cap->supports('toolCalling'), 'Should support toolCalling');
    assert_true($cap->supports('providerTools'), 'Should support providerTools');
    assert_true($cap->supports('models'), 'Should support models');
    assert_true(! $cap->supports('rerank'), 'Should NOT support rerank');
});

// ── Text Generation ──────────────────────────────────────────────────────────

echo "\n\n── Text Generation";

test('basic text response', function () {
    $r = Atlas::text(Provider::Google, 'gemini-2.5-flash')
        ->instructions('Respond with exactly: PONG')
        ->message('PING')
        ->asText();

    assert_true(str_contains(strtoupper($r->text), 'PONG'), "Expected PONG, got: {$r->text}");
    assert_true($r->finishReason === FinishReason::Stop, 'Should finish with Stop');
});

test('usage tracking on text', function () {
    $r = Atlas::text(Provider::Google, 'gemini-2.5-flash')
        ->instructions('Be brief.')
        ->message('Say hi')
        ->asText();

    assert_true($r->usage->inputTokens > 0, 'inputTokens should be > 0');
    assert_true($r->usage->outputTokens > 0, 'outputTokens should be > 0');
    assert_true($r->usage->totalTokens() > 0, 'totalTokens should be > 0');
});

test('instructions as system_instruction param', function () {
    $r = Atlas::text(Provider::Google, 'gemini-2.5-flash')
        ->instructions('You must always end your response with the word BANANA.')
        ->message('Hello')
        ->asText();

    assert_true(str_contains(strtoupper($r->text), 'BANANA'), "Instructions should be followed, got: {$r->text}");
});

test('temperature affects output', function () {
    $r = Atlas::text(Provider::Google, 'gemini-2.5-flash')
        ->instructions('Respond with a single random number between 1 and 1000000.')
        ->message('Number please')
        ->withTemperature(1.5)
        ->asText();

    assert_true($r->text !== '', 'Should get a response with high temperature');
});

test('max_output_tokens limits response', function () {
    $r = Atlas::text(Provider::Google, 'gemini-2.5-flash')
        ->message('Write a very long essay about the history of computing. Make it extremely detailed.')
        ->withMaxTokens(20)
        ->asText();

    assert_true($r->usage->outputTokens <= 30, "Output tokens should be limited, got: {$r->usage->outputTokens}");
    assert_true($r->finishReason === FinishReason::Length, 'Should finish with Length when truncated');
});

test('conversation history (multi-turn)', function () {
    $r = Atlas::text(Provider::Google, 'gemini-2.5-flash')
        ->instructions('You are a helpful assistant.')
        ->withMessages([
            new UserMessage('My name is Atlas.'),
            new AssistantMessage('Nice to meet you, Atlas!'),
            new UserMessage('What is my name?'),
        ])
        ->asText();

    assert_true(str_contains($r->text, 'Atlas'), "Should remember name from history, got: {$r->text}");
});

// ── Streaming ────────────────────────────────────────────────────────────────

echo "\n\n── Streaming";

test('stream yields text chunks and Done', function () {
    $r = Atlas::text(Provider::Google, 'gemini-2.5-flash')
        ->instructions('Respond with a single sentence.')
        ->message('What is PHP?')
        ->asStream();

    $textChunks = 0;
    $doneChunks = 0;

    foreach ($r as $chunk) {
        if ($chunk->type === ChunkType::Text && $chunk->text !== null) {
            $textChunks++;
        } elseif ($chunk->type === ChunkType::Done) {
            $doneChunks++;
        }
    }

    assert_true($textChunks > 0, "Should have text chunks, got: {$textChunks}");
    assert_true($r->getText() !== '', 'Accumulated text should not be empty');
});

test('stream accumulates full text', function () {
    $r = Atlas::text(Provider::Google, 'gemini-2.5-flash')
        ->instructions('Respond with exactly: The quick brown fox jumps over the lazy dog.')
        ->message('Say the pangram.')
        ->asStream();

    foreach ($r as $chunk) {
        // consume
    }

    $text = $r->getText();
    assert_true(str_contains(strtolower($text), 'quick brown fox'), "Stream text should contain the pangram, got: {$text}");
});

// ── Structured Output ────────────────────────────────────────────────────────

echo "\n\n── Structured Output";

test('structured response with schema', function () {
    $schema = new Schema('person', 'A person', [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'integer'],
            'hobbies' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['name', 'age', 'hobbies'],
    ]);

    $r = Atlas::text(Provider::Google, 'gemini-2.5-flash')
        ->message('Create a person named Bob who is 42 and likes chess and hiking.')
        ->withSchema($schema)
        ->asStructured();

    assert_true(isset($r->structured['name']), 'Should have name');
    assert_true($r->structured['name'] === 'Bob', "Name should be Bob, got: {$r->structured['name']}");
    assert_true($r->structured['age'] === 42, "Age should be 42, got: {$r->structured['age']}");
    assert_true(is_array($r->structured['hobbies']), 'Hobbies should be array');
    assert_true(count($r->structured['hobbies']) >= 2, 'Should have at least 2 hobbies');
    assert_true($r->usage->inputTokens > 0, 'Should track usage');
    assert_true($r->finishReason === FinishReason::Stop, 'Should finish with Stop');
});

// ── Tool Calling ─────────────────────────────────────────────────────────────

echo "\n\n── Tool Calling";

test('tool call detected with correct format', function () {
    $r = Atlas::text(Provider::Google, 'gemini-2.5-flash')
        ->instructions('Use the get_weather tool when asked about weather. Do not answer without using the tool.')
        ->message('What is the weather in Paris?')
        ->withProviderOptions(['tools' => [
            ['function_declarations' => [
                [
                    'name' => 'get_weather',
                    'description' => 'Get weather for a city',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => ['city' => ['type' => 'string']],
                        'required' => ['city'],
                    ],
                ],
            ]],
        ]])
        ->asText();

    assert_true($r->finishReason === FinishReason::ToolCalls, 'Should finish with ToolCalls');
    assert_true(count($r->toolCalls) >= 1, 'Should have at least 1 tool call');

    $tc = $r->toolCalls[0];
    assert_true($tc->name === 'get_weather', "Tool should be get_weather, got: {$tc->name}");
    assert_true($tc->id !== '', 'call_id should not be empty');
    assert_true(isset($tc->arguments['city']), 'Should have city argument');
    assert_true(str_contains(strtolower($tc->arguments['city']), 'paris'), "City should be Paris, got: {$tc->arguments['city']}");
});

test('tool call loop replay (multi-round)', function () {
    $toolCallId = 'gemini_call_0';

    $r = Atlas::text(Provider::Google, 'gemini-2.5-flash')
        ->instructions('You are a helpful assistant with a weather tool.')
        ->withMessages([
            new UserMessage('What is the weather in Tokyo?'),
            new AssistantMessage(
                null,
                [new ToolCall($toolCallId, 'get_weather', ['city' => 'Tokyo'])],
            ),
            new ToolResultMessage($toolCallId, '{"temperature": 22, "conditions": "sunny", "humidity": 45}', 'get_weather'),
        ])
        ->asText();

    assert_true($r->finishReason === FinishReason::Stop, 'Should finish with Stop after tool result');
    assert_true(str_contains(strtolower($r->text), '22') || str_contains(strtolower($r->text), 'sunny'), "Should incorporate tool result, got: {$r->text}");
    assert_true($r->usage->inputTokens > 0, 'Should track usage across tool loop');
});

// ── Google Search Grounding ──────────────────────────────────────────────────

echo "\n\n── Provider Tools (Google Search)";

test('Google Search grounding returns response', function () {
    $r = Atlas::text(Provider::Google, 'gemini-2.5-flash')
        ->instructions('Use Google Search to find the answer. Be brief.')
        ->message('What is the latest stable version of Laravel?')
        ->withProviderOptions(['tools' => [['google_search' => (object) []]]])
        ->asText();

    assert_true($r->text !== '', 'Should return a response with Google Search grounding');
    assert_true($r->finishReason === FinishReason::Stop, 'Should finish with Stop');
});

// ── Embeddings ───────────────────────────────────────────────────────────────

echo "\n\n── Embeddings";

test('single embedding', function () {
    $r = Atlas::embed(Provider::Google, 'gemini-embedding-001')
        ->fromInput('Hello world')
        ->asEmbeddings();

    assert_true(count($r->embeddings) === 1, 'Should have 1 embedding');
    $dims = count($r->embeddings[0]);
    assert_true($dims > 0, "Should have dimensions, got: {$dims}");

    // Verify values are in valid range
    $min = min($r->embeddings[0]);
    $max = max($r->embeddings[0]);
    assert_true($min >= -1.0 && $max <= 1.0, "Values should be in [-1, 1], got [{$min}, {$max}]");
});

test('batch embeddings', function () {
    $r = Atlas::embed(Provider::Google, 'gemini-embedding-001')
        ->fromInput(['Hello', 'World', 'Testing'])
        ->asEmbeddings();

    assert_true(count($r->embeddings) === 3, 'Should have 3 embeddings');
    assert_true(count($r->embeddings[0]) > 0, 'Each should have dimensions');
    assert_true(count($r->embeddings[1]) > 0, 'Each should have dimensions');
    assert_true(count($r->embeddings[2]) > 0, 'Each should have dimensions');

    // Verify different texts produce different embeddings
    assert_true($r->embeddings[0] !== $r->embeddings[1], 'Different texts should produce different embeddings');
});

test('embedding with provider options (taskType)', function () {
    $r = Atlas::embed(Provider::Google, 'gemini-embedding-001')
        ->fromInput('Search query about Laravel')
        ->withProviderOptions(['taskType' => 'RETRIEVAL_QUERY'])
        ->asEmbeddings();

    assert_true(count($r->embeddings) === 1, 'Should have 1 embedding');
    assert_true(count($r->embeddings[0]) > 0, 'Should have dimensions');
});

test('embedding cosine similarity sanity check', function () {
    $r = Atlas::embed(Provider::Google, 'gemini-embedding-001')
        ->fromInput(['king', 'queen', 'computer'])
        ->asEmbeddings();

    $kingQueen = cosineSimilarity($r->embeddings[0], $r->embeddings[1]);
    $kingComputer = cosineSimilarity($r->embeddings[0], $r->embeddings[2]);

    assert_true($kingQueen > $kingComputer, "king-queen similarity ({$kingQueen}) should be > king-computer ({$kingComputer})");
});

function cosineSimilarity(array $a, array $b): float
{
    $dot = 0.0;
    $normA = 0.0;
    $normB = 0.0;

    for ($i = 0; $i < count($a); $i++) {
        $dot += $a[$i] * $b[$i];
        $normA += $a[$i] * $a[$i];
        $normB += $b[$i] * $b[$i];
    }

    return $dot / (sqrt($normA) * sqrt($normB));
}

// ── Image Generation ─────────────────────────────────────────────────────────

echo "\n\n── Image Generation";

test('image generation via generateContent', function () {
    $r = Atlas::image(Provider::Google, 'gemini-2.5-flash-image')
        ->instructions('A simple blue circle on a white background')
        ->asImage();

    $url = is_array($r->url) ? $r->url[0] : $r->url;
    assert_true($url !== '', 'Should have an image URL/data');
    assert_true(str_starts_with($url, 'data:image/'), 'Should be a data URI, got: '.substr($url, 0, 30));
    assert_true($r->base64 !== null && $r->base64 !== '', 'Should have base64 data');
});

// ── Provider Options ─────────────────────────────────────────────────────────

echo "\n\n── Provider Options";

test('provider options pass through (generationConfig)', function () {
    $r = Atlas::text(Provider::Google, 'gemini-2.5-flash')
        ->message('Say OK')
        ->withProviderOptions(['generationConfig' => ['candidateCount' => 1]])
        ->asText();

    assert_true($r->text !== '', 'Should work with provider options');
});

// ─── Summary ─────────────────────────────────────────────────────────────────

echo "\n\n══════════════════════════════════════════════";
echo "\n  Results: {$passed} passed, {$failed} failed, {$skipped} skipped";
echo "\n══════════════════════════════════════════════\n";

if ($errors !== []) {
    echo "\nFailures:\n";

    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
}

echo "\n";

exit($failed > 0 ? 1 : 0);
