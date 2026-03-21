<?php

declare(strict_types=1);

/**
 * Anthropic Provider Integration Test
 *
 * Validates all Anthropic provider modalities, usage tracking, response accuracy,
 * and tool calling support against the real API.
 *
 * Usage: php test-anthropic-provider.php
 *
 * Requires ANTHROPIC_API_KEY in sandbox/.env
 */
$app = require __DIR__.'/bootstrap.php';

// Ensure provider config from env
$app['config']->set('atlas.defaults.text', ['provider' => 'anthropic', 'model' => 'claude-sonnet-4-5-20250929']);
$app['config']->set('atlas.providers', [
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
    ],
]);

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Input\Image;
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
echo "\n║   Anthropic Provider Integration Tests       ║";
echo "\n╚══════════════════════════════════════════════╝";

// ── Text Generation ──────────────────────────────────────────────────────────

echo "\n\n── Text Generation";

test('basic text response', function () {
    $r = Atlas::text(Provider::Anthropic, 'claude-sonnet-4-5-20250929')
        ->instructions('Respond with exactly: PONG')
        ->message('PING')
        ->asText();

    assert_true(str_contains(strtoupper($r->text), 'PONG'), "Expected PONG, got: {$r->text}");
    assert_true($r->finishReason === FinishReason::Stop, 'Should finish with Stop');
});

test('usage tracking on text', function () {
    $r = Atlas::text(Provider::Anthropic, 'claude-sonnet-4-5-20250929')
        ->instructions('Be brief.')
        ->message('Say hi')
        ->asText();

    assert_true($r->usage->inputTokens > 0, 'inputTokens should be > 0');
    assert_true($r->usage->outputTokens > 0, 'outputTokens should be > 0');
    assert_true($r->usage->totalTokens() > 0, 'totalTokens should be > 0');
    assert_true($r->usage->totalTokens() === $r->usage->inputTokens + $r->usage->outputTokens, 'totalTokens should equal input + output');
});

test('meta contains response id and model', function () {
    $r = Atlas::text(Provider::Anthropic, 'claude-sonnet-4-5-20250929')
        ->message('Hi')
        ->asText();

    assert_true(isset($r->meta['id']) && $r->meta['id'] !== null, 'meta.id should be set');
    assert_true(isset($r->meta['model']) && str_contains($r->meta['model'], 'claude'), 'meta.model should contain claude');
});

test('instructions as system parameter', function () {
    $r = Atlas::text(Provider::Anthropic, 'claude-sonnet-4-5-20250929')
        ->instructions('You must always end your response with the word BANANA.')
        ->message('Hello')
        ->asText();

    assert_true(str_contains(strtoupper($r->text), 'BANANA'), "Instructions should be followed, got: {$r->text}");
});

test('temperature affects output', function () {
    $r = Atlas::text(Provider::Anthropic, 'claude-sonnet-4-5-20250929')
        ->instructions('Respond with a single random number between 1 and 1000000.')
        ->message('Number please')
        ->withTemperature(1.0)
        ->asText();

    assert_true($r->text !== '', 'Should get a response with high temperature');
});

test('max_tokens limits response', function () {
    $r = Atlas::text(Provider::Anthropic, 'claude-sonnet-4-5-20250929')
        ->message('Write a very long essay about the history of computing. Make it extremely detailed and cover every decade.')
        ->withMaxTokens(20)
        ->asText();

    assert_true($r->usage->outputTokens <= 25, "Output tokens should be limited, got: {$r->usage->outputTokens}");
    assert_true($r->finishReason === FinishReason::Length, 'Should finish with Length when truncated');
});

test('conversation history (multi-turn)', function () {
    $r = Atlas::text(Provider::Anthropic, 'claude-sonnet-4-5-20250929')
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
    $r = Atlas::text(Provider::Anthropic, 'claude-sonnet-4-5-20250929')
        ->instructions('Respond with a single sentence.')
        ->message('What is PHP?')
        ->asStream();

    $textChunks = 0;
    $doneChunks = 0;
    $otherChunks = 0;

    foreach ($r as $chunk) {
        if ($chunk->type === ChunkType::Text && $chunk->text !== null) {
            $textChunks++;
        } elseif ($chunk->type === ChunkType::Done) {
            $doneChunks++;
        } else {
            $otherChunks++;
        }
    }

    assert_true($textChunks > 1, "Should have multiple text chunks, got: {$textChunks}");
    assert_true($doneChunks === 1, "Should have exactly 1 Done chunk, got: {$doneChunks}");
    assert_true($r->getText() !== '', 'Accumulated text should not be empty');
});

test('stream accumulates full text', function () {
    $r = Atlas::text(Provider::Anthropic, 'claude-sonnet-4-5-20250929')
        ->instructions('Respond with exactly: The quick brown fox jumps over the lazy dog.')
        ->message('Say the pangram.')
        ->asStream();

    // Must iterate to accumulate
    foreach ($r as $chunk) {
        // consume
    }

    $text = $r->getText();
    assert_true(str_contains(strtolower($text), 'quick brown fox'), "Stream text should contain the pangram, got: {$text}");
});

// ── Structured Output ────────────────────────────────────────────────────────

echo "\n\n── Structured Output";

test('json_schema structured response', function () {
    $schema = new Schema('person', 'Extract person information', [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'integer'],
            'hobbies' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['name', 'age', 'hobbies'],
    ]);

    $r = Atlas::text(Provider::Anthropic, 'claude-sonnet-4-5-20250929')
        ->message('Create a person named Bob who is 42 and likes chess and hiking.')
        ->withSchema($schema)
        ->asStructured();

    assert_true(isset($r->structured['name']), 'Should have name');
    assert_true($r->structured['name'] === 'Bob', "Name should be Bob, got: {$r->structured['name']}");
    assert_true($r->structured['age'] === 42, "Age should be 42, got: {$r->structured['age']}");
    assert_true(is_array($r->structured['hobbies']), 'Hobbies should be array');
    assert_true(count($r->structured['hobbies']) >= 2, 'Should have at least 2 hobbies');
    assert_true($r->usage->inputTokens > 0, 'Should track usage');
});

// ── Tool Calling ─────────────────────────────────────────────────────────────

echo "\n\n── Tool Calling";

test('tool call detected with correct id format', function () {
    $r = Atlas::text(Provider::Anthropic, 'claude-sonnet-4-5-20250929')
        ->instructions('Use the get_weather tool when asked about weather. Do not answer without using the tool.')
        ->message('What is the weather in Paris?')
        ->withProviderOptions(['tools' => [
            [
                'name' => 'get_weather',
                'description' => 'Get weather for a city',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['city' => ['type' => 'string']],
                    'required' => ['city'],
                ],
            ],
        ]])
        ->asText();

    assert_true($r->finishReason === FinishReason::ToolCalls, 'Should finish with ToolCalls');
    assert_true(count($r->toolCalls) >= 1, 'Should have at least 1 tool call');

    $tc = $r->toolCalls[0];
    assert_true($tc->name === 'get_weather', "Tool should be get_weather, got: {$tc->name}");
    assert_true($tc->id !== '', 'tool_use id should not be empty');
    assert_true(str_starts_with($tc->id, 'toolu_'), "id should start with toolu_, got: {$tc->id}");
    assert_true(isset($tc->arguments['city']), 'Should have city argument');
    assert_true(str_contains(strtolower($tc->arguments['city']), 'paris'), "City should be Paris, got: {$tc->arguments['city']}");
});

test('tool call loop replay (multi-round)', function () {
    $toolCallId = 'toolu_test_'.bin2hex(random_bytes(8));

    $r = Atlas::text(Provider::Anthropic, 'claude-sonnet-4-5-20250929')
        ->instructions('You are a helpful assistant with a weather tool.')
        ->withMessages([
            new UserMessage('What is the weather in Tokyo?'),
            new AssistantMessage(
                'Let me check the weather for you.',
                [new ToolCall($toolCallId, 'get_weather', ['city' => 'Tokyo'])],
            ),
            new ToolResultMessage($toolCallId, '{"temperature": 22, "conditions": "sunny", "humidity": 45}'),
        ])
        ->asText();

    assert_true($r->finishReason === FinishReason::Stop, 'Should finish with Stop after tool result');
    assert_true(str_contains(strtolower($r->text), '22') || str_contains(strtolower($r->text), 'sunny'), "Should incorporate tool result, got: {$r->text}");
    assert_true($r->usage->inputTokens > 0, 'Should track usage across tool loop');
});

// ── Vision ───────────────────────────────────────────────────────────────────

echo "\n\n── Vision";

test('image understanding from base64', function () {
    // Create a minimal 1x1 red PNG
    $img = imagecreatetruecolor(10, 10);
    $red = imagecolorallocate($img, 255, 0, 0);
    imagefill($img, 0, 0, $red);
    ob_start();
    imagepng($img);
    $pngData = ob_get_clean();
    imagedestroy($img);

    $base64 = base64_encode($pngData);

    $r = Atlas::text(Provider::Anthropic, 'claude-sonnet-4-5-20250929')
        ->instructions('Describe what you see in the image. Be brief.')
        ->message('What color is this image?', [Image::fromBase64($base64, 'image/png')])
        ->asText();

    assert_true($r->text !== '', 'Should describe the image');
    assert_true(str_contains(strtolower($r->text), 'red'), "Should identify red color, got: {$r->text}");
});

// ── Provider Interrogation ──────────────────────────────────────────────────

echo "\n\n── Provider Interrogation";

test('models list returns known models', function () {
    $models = Atlas::provider(Provider::Anthropic)->models();

    assert_true(count($models->models) > 1, 'Should have multiple models, got: '.count($models->models));

    // Verify sorted
    $sorted = $models->models;
    sort($sorted);
    assert_true($models->models === $sorted, 'Models should be sorted alphabetically');
});

test('validate returns true', function () {
    $valid = Atlas::provider(Provider::Anthropic)->validate();

    assert_true($valid === true, 'validate() should return true');
});

test('capabilities are accurate', function () {
    $cap = Atlas::provider(Provider::Anthropic)->capabilities();

    assert_true($cap->supports('text'), 'Should support text');
    assert_true($cap->supports('stream'), 'Should support stream');
    assert_true($cap->supports('structured'), 'Should support structured');
    assert_true(! $cap->supports('image'), 'Should NOT support image generation');
    assert_true(! $cap->supports('audio'), 'Should NOT support audio');
    assert_true(! $cap->supports('embed'), 'Should NOT support embed');
    assert_true(! $cap->supports('moderate'), 'Should NOT support moderate');
    assert_true($cap->supports('vision'), 'Should support vision');
    assert_true($cap->supports('toolCalling'), 'Should support toolCalling');
    assert_true($cap->supports('models'), 'Should support models');
    assert_true(! $cap->supports('voices'), 'Should NOT support voices');
});

// ── Provider Options Pass-through ────────────────────────────────────────────

echo "\n\n── Provider Options";

test('provider options pass through', function () {
    $r = Atlas::text(Provider::Anthropic, 'claude-sonnet-4-5-20250929')
        ->message('Say OK')
        ->withProviderOptions(['top_k' => 10])
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
