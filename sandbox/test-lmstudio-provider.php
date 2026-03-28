<?php

declare(strict_types=1);

/**
 * LM Studio Custom Provider Integration Test
 *
 * Validates the ChatCompletions driver against a local LM Studio instance.
 * Auto-discovers the first available model and tests text, streaming,
 * structured output, and tool calling.
 *
 * Usage: php test-lmstudio-provider.php
 *
 * Requires LM Studio running at http://10.0.0.41:1234
 */
$app = require __DIR__.'/bootstrap.php';

// Register LM Studio as a config-driven custom provider
$app['config']->set('atlas.providers.lmstudio', [
    'driver' => 'chat_completions',
    'api_key' => 'lm-studio',
    'base_url' => 'http://10.0.0.41:1234/v1',
    'capabilities' => [
        'image' => false,
        'audio' => false,
        'audioToText' => false,
        'video' => false,
        'moderate' => false,
    ],
]);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Messages\ToolResultMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Tools\ToolDefinition;

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

// ─── Discover Model ──────────────────────────────────────────────────────────

echo '╔══════════════════════════════════════════════╗';
echo "\n║   LM Studio Custom Provider Tests            ║";
echo "\n╚══════════════════════════════════════════════╝";

echo "\n\n── Model Discovery";

$model = null;

test('models endpoint returns available models', function () use (&$model) {
    $models = Atlas::provider('lmstudio')->models();

    assert_true(count($models->models) > 0, 'Should have at least 1 model, got: '.count($models->models));

    // Pick the first model
    $model = $models->models[0];
    echo "\n    → Found ".count($models->models).' models';
    echo "\n    → Using: {$model}";
});

if ($model === null) {
    echo "\n\n  ✗ Cannot continue — no models available.\n";
    exit(1);
}

// ─── Text Generation ─────────────────────────────────────────────────────────

echo "\n\n── Text Generation (model: {$model})";

test('basic text response', function () use ($model) {
    $r = Atlas::text('lmstudio', $model)
        ->instructions('Respond with exactly: PONG')
        ->message('PING')
        ->asText();

    assert_true($r->text !== '', 'Response should not be empty');
    assert_true(str_contains(strtoupper($r->text), 'PONG'), "Expected PONG, got: {$r->text}");
    assert_true($r->finishReason === FinishReason::Stop, 'Should finish with Stop');
});

test('usage tracking on text', function () use ($model) {
    $r = Atlas::text('lmstudio', $model)
        ->instructions('Be brief.')
        ->message('Say hi')
        ->asText();

    assert_true($r->usage->inputTokens > 0, 'inputTokens should be > 0');
    assert_true($r->usage->outputTokens > 0, 'outputTokens should be > 0');
});

test('meta contains response id and model', function () use ($model) {
    $r = Atlas::text('lmstudio', $model)
        ->message('Hi')
        ->asText();

    assert_true(isset($r->meta['id']), 'meta.id should be set');
    assert_true(isset($r->meta['model']), 'meta.model should be set');
    echo "\n    → id: {$r->meta['id']}";
    echo "\n    → model: {$r->meta['model']}";
});

test('instructions are sent as system message', function () use ($model) {
    $r = Atlas::text('lmstudio', $model)
        ->instructions('You are a pirate. Always talk like a pirate.')
        ->message('Hello, who are you?')
        ->asText();

    // Just verify instructions reach the model and influence output
    assert_true($r->text !== '', 'Should get a response with instructions, got empty');
    echo "\n    → Response: ".substr($r->text, 0, 100);
});

test('conversation history (multi-turn)', function () use ($model) {
    $r = Atlas::text('lmstudio', $model)
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

test('stream yields text chunks and Done', function () use ($model) {
    $r = Atlas::text('lmstudio', $model)
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

    assert_true($textChunks > 1, "Should have multiple text chunks, got: {$textChunks}");
    assert_true($doneChunks === 1, "Should have exactly 1 Done chunk, got: {$doneChunks}");
    assert_true($r->getText() !== '', 'Accumulated text should not be empty');

    echo "\n    → {$textChunks} text chunks, text: ".substr($r->getText(), 0, 80).'...';
});

// ── Structured Output ────────────────────────────────────────────────────────

echo "\n\n── Structured Output";

test('json_schema structured response', function () use ($model) {
    $schema = new Schema('person', 'A person', [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'integer'],
        ],
        'required' => ['name', 'age'],
        'additionalProperties' => false,
    ]);

    $r = Atlas::text('lmstudio', $model)
        ->message('Create a person named Bob who is 42.')
        ->withSchema($schema)
        ->asStructured();

    assert_true(isset($r->structured['name']), 'Should have name');
    assert_true(isset($r->structured['age']), 'Should have age');
    echo "\n    → name: {$r->structured['name']}, age: {$r->structured['age']}";
});

// ── Tool Calling ─────────────────────────────────────────────────────────────

echo "\n\n── Tool Calling";

test('tool call detected', function () use ($model) {
    $tools = [
        new ToolDefinition('get_weather', 'Get weather for a city', [
            'type' => 'object',
            'properties' => ['city' => ['type' => 'string']],
            'required' => ['city'],
            'additionalProperties' => false,
        ]),
    ];

    $r = Atlas::text('lmstudio', $model)
        ->instructions('Use the get_weather tool when asked about weather. Do not answer without using the tool.')
        ->message('What is the weather in Paris?')
        ->withProviderOptions(['tools' => array_map(fn (ToolDefinition $t) => [
            'type' => 'function',
            'function' => [
                'name' => $t->name,
                'description' => $t->description,
                'parameters' => $t->parameters,
            ],
        ], $tools)])
        ->asText();

    assert_true($r->finishReason === FinishReason::ToolCalls, "Should finish with ToolCalls, got: {$r->finishReason->value}");
    assert_true(count($r->toolCalls) >= 1, 'Should have at least 1 tool call');

    $tc = $r->toolCalls[0];
    assert_true($tc->name === 'get_weather', "Tool should be get_weather, got: {$tc->name}");
    assert_true($tc->id !== '', 'call id should not be empty');
    assert_true(isset($tc->arguments['city']), 'Should have city argument');

    echo "\n    → Tool: {$tc->name}({$tc->id})";
    echo "\n    → Args: ".json_encode($tc->arguments);
});

test('tool call loop replay (multi-round)', function () use ($model) {
    $toolCallId = 'call_test_'.bin2hex(random_bytes(8));

    $r = Atlas::text('lmstudio', $model)
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
    assert_true($r->text !== '', 'Should have a response');
    echo "\n    → Response: ".substr($r->text, 0, 100).'...';
});

// ── Provider Options ─────────────────────────────────────────────────────────

echo "\n\n── Provider Options";

test('provider options pass through (temperature)', function () use ($model) {
    $r = Atlas::text('lmstudio', $model)
        ->message('Say OK')
        ->withProviderOptions(['temperature' => 0])
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
