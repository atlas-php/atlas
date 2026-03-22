<?php

declare(strict_types=1);

/**
 * xAI Provider Integration Test
 *
 * Validates all xAI provider modalities, usage tracking, response accuracy,
 * and provider tool support against the real API.
 *
 * Usage: php test-xai-provider.php
 *
 * Requires XAI_API_KEY in sandbox/.env
 */
$app = require __DIR__.'/bootstrap.php';

// Ensure provider config from env
$app['config']->set('atlas.defaults.text', ['provider' => 'xai', 'model' => 'grok-3-mini']);
$app['config']->set('atlas.providers', [
    'xai' => [
        'api_key' => env('XAI_API_KEY'),
        'url' => env('XAI_URL', 'https://api.x.ai/v1'),
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
use Atlasphp\Atlas\Tools\ToolDefinition;

// ─── Storage Setup ───────────────────────────────────────────────────────────

$storageDir = __DIR__.'/storage/providers/xai';

// Wipe previous test output
if (is_dir($storageDir)) {
    $files = glob("{$storageDir}/*");
    if ($files !== false) {
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
} else {
    mkdir($storageDir, 0755, true);
}

function saveFile(string $name, string $data, string $ext = 'bin'): void
{
    global $storageDir;

    $path = "{$storageDir}/{$name}.{$ext}";
    file_put_contents($path, $data);
    $size = strlen($data);
    echo " → saved {$name}.{$ext} (".number_format($size).' bytes)';
}

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
echo "\n║   xAI Provider Integration Tests             ║";
echo "\n╚══════════════════════════════════════════════╝";

// ── Text Generation ──────────────────────────────────────────────────────────

echo "\n\n── Text Generation";

test('basic text response', function () {
    $r = Atlas::text(Provider::xAI, 'grok-3-mini')
        ->instructions('Respond with exactly: PONG')
        ->message('PING')
        ->asText();

    assert_true(str_contains(strtoupper($r->text), 'PONG'), "Expected PONG, got: {$r->text}");
    assert_true($r->finishReason === FinishReason::Stop, 'Should finish with Stop');
});

test('usage tracking on text', function () {
    $r = Atlas::text(Provider::xAI, 'grok-3-mini')
        ->instructions('Be brief.')
        ->message('Say hi')
        ->asText();

    assert_true($r->usage->inputTokens > 0, 'inputTokens should be > 0');
    assert_true($r->usage->outputTokens > 0, 'outputTokens should be > 0');
    assert_true($r->usage->totalTokens() > 0, 'totalTokens should be > 0');
});

test('meta contains response id and model', function () {
    $r = Atlas::text(Provider::xAI, 'grok-3-mini')
        ->message('Hi')
        ->asText();

    assert_true(isset($r->meta['id']) && $r->meta['id'] !== null, 'meta.id should be set');
    assert_true(isset($r->meta['model']), 'meta.model should be set');
});

test('instructions go as system message (not top-level param)', function () {
    $r = Atlas::text(Provider::xAI, 'grok-3-mini')
        ->instructions('You must always end your response with the word BANANA.')
        ->message('Hello')
        ->asText();

    assert_true(str_contains(strtoupper($r->text), 'BANANA'), "Instructions should be followed, got: {$r->text}");
});

test('conversation history (multi-turn)', function () {
    $r = Atlas::text(Provider::xAI, 'grok-3-mini')
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
    $r = Atlas::text(Provider::xAI, 'grok-3-mini')
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
});

test('stream accumulates full text', function () {
    $r = Atlas::text(Provider::xAI, 'grok-3-mini')
        ->instructions('Respond with exactly: The quick brown fox jumps over the lazy dog.')
        ->message('Say the pangram.')
        ->asStream();

    foreach ($r as $chunk) {
        // consume
    }

    $text = $r->getText();
    assert_true(str_contains(strtolower($text), 'quick brown fox'), "Stream text should contain the pangram, got: {$text}");

    $usage = $r->getUsage();
    assert_true($usage !== null, 'Stream should have usage after iteration');
    assert_true($usage->inputTokens > 0, "Stream inputTokens should be > 0, got: {$usage->inputTokens}");
    assert_true($usage->outputTokens > 0, "Stream outputTokens should be > 0, got: {$usage->outputTokens}");
});

// ── Structured Output ────────────────────────────────────────────────────────

echo "\n\n── Structured Output";

test('json_schema structured response', function () {
    $schema = new Schema('person', 'A person', [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'integer'],
            'hobbies' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['name', 'age', 'hobbies'],
        'additionalProperties' => false,
    ]);

    $r = Atlas::text(Provider::xAI, 'grok-3-mini')
        ->message('Create a person named Bob who is 42 and likes chess and hiking.')
        ->withSchema($schema)
        ->asStructured();

    assert_true(isset($r->structured['name']), 'Should have name');
    assert_true($r->structured['name'] === 'Bob', "Name should be Bob, got: {$r->structured['name']}");
    assert_true($r->structured['age'] === 42, "Age should be 42, got: {$r->structured['age']}");
    assert_true(is_array($r->structured['hobbies']), 'Hobbies should be array');
    assert_true(count($r->structured['hobbies']) >= 2, 'Should have at least 2 hobbies');
});

// ── Tool Calling ─────────────────────────────────────────────────────────────

echo "\n\n── Tool Calling";

test('tool call detected with correct call_id format', function () {
    $tools = [
        new ToolDefinition('get_weather', 'Get weather for a city', [
            'type' => 'object',
            'properties' => ['city' => ['type' => 'string']],
            'required' => ['city'],
            'additionalProperties' => false,
        ]),
    ];

    $r = Atlas::text(Provider::xAI, 'grok-3-mini')
        ->instructions('Use the get_weather tool when asked about weather. Do not answer without using the tool.')
        ->message('What is the weather in Paris?')
        ->withProviderOptions(['tools' => array_map(fn (ToolDefinition $t) => [
            'type' => 'function',
            'name' => $t->name,
            'description' => $t->description,
            'parameters' => $t->parameters,
            'strict' => true,
        ], $tools)])
        ->asText();

    assert_true($r->finishReason === FinishReason::ToolCalls, 'Should finish with ToolCalls');
    assert_true(count($r->toolCalls) >= 1, 'Should have at least 1 tool call');

    $tc = $r->toolCalls[0];
    assert_true($tc->name === 'get_weather', "Tool should be get_weather, got: {$tc->name}");
    assert_true($tc->id !== '', 'call_id should not be empty');
    assert_true(isset($tc->arguments['city']), 'Should have city argument');
});

test('tool call loop replay (multi-round)', function () {
    $toolCallId = 'call_test_'.bin2hex(random_bytes(8));

    $r = Atlas::text(Provider::xAI, 'grok-3-mini')
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
});

// ── Provider Tools ───────────────────────────────────────────────────────────

echo "\n\n── Provider Tools";

test('web search provider tool (grok-4)', function () {
    $r = Atlas::text(Provider::xAI, 'grok-4-fast-non-reasoning')
        ->instructions('Use web search to answer. Be brief.')
        ->message('What is the latest PHP version released in 2025 or 2026?')
        ->withProviderOptions(['tools' => [['type' => 'web_search']]])
        ->asText();

    assert_true($r->text !== '', 'Should return a response with web data');
    assert_true($r->finishReason === FinishReason::Stop, 'Should finish with Stop');
    // Provider tool calls should be captured
    assert_true($r->providerToolCalls !== [], 'Should capture provider tool calls');
    assert_true($r->providerToolCalls[0]['type'] === 'web_search_call', 'Should be web_search_call type');
});

test('X search provider tool (grok-4)', function () {
    $r = Atlas::text(Provider::xAI, 'grok-4-fast-non-reasoning')
        ->instructions('Search X/Twitter to answer. Be brief.')
        ->message('What are people saying about PHP 8.4?')
        ->withProviderOptions(['tools' => [['type' => 'x_search']]])
        ->asText();

    assert_true($r->text !== '', 'Should return a response with X search data');
});

// ── Image Generation ─────────────────────────────────────────────────────────

echo "\n\n── Image Generation";

test('image generation + save to disk', function () {
    $r = Atlas::image(Provider::xAI, 'grok-imagine-image')
        ->instructions('A simple blue square on a white background')
        ->asImage();

    assert_true($r->url !== '', 'Should have an image URL');
    echo "\n    → URL: {$r->url}";
    echo "\n    → Revised prompt: ".($r->revisedPrompt ?? '(none)');

    // Download and save the image
    $imgData = file_get_contents($r->url);
    if ($imgData !== false) {
        $timestamp = date('His');
        saveFile("image-{$timestamp}", $imgData, 'png');
    }
});

// ── Audio TTS ────────────────────────────────────────────────────────────────

echo "\n\n── Audio TTS";

test('text-to-speech with default voice (eve) + save', function () {
    $r = Atlas::audio(Provider::xAI, 'tts-1')
        ->instructions('Hello, this is a test of Atlas text to speech.')
        ->asAudio();

    $decoded = base64_decode($r->data);
    assert_true(strlen($decoded) > 100, 'Audio data should be substantial');
    assert_true($r->format === 'mp3', "Format should be mp3, got: {$r->format}");

    saveFile('tts-eve', $decoded, 'mp3');
});

test('text-to-speech with each voice + save', function () {
    $voices = ['ara', 'eve', 'leo', 'rex', 'sal'];

    foreach ($voices as $voice) {
        $r = Atlas::audio(Provider::xAI, 'tts-1')
            ->instructions('The quick brown fox jumps over the lazy dog.')
            ->withVoice($voice)
            ->asAudio();

        $decoded = base64_decode($r->data);
        assert_true(strlen($decoded) > 50, "Voice {$voice} should produce audio data");

        saveFile("tts-{$voice}", $decoded, 'mp3');
    }
});

// ── Video Generation ─────────────────────────────────────────────────────────

echo "\n\n── Video Generation";

test('async video generation (5s) + save to disk', function () {
    echo "\n    → Submitting video generation request...";

    $r = Atlas::video(Provider::xAI, 'grok-imagine-video')
        ->instructions('A cat sitting on a windowsill watching rain fall outside')
        ->withDuration(5)
        ->withRatio('16:9')
        ->asVideo();

    assert_true($r->url !== '', 'Should have a video URL');
    echo "\n    → Video URL: {$r->url}";
    echo "\n    → Duration: ".($r->duration ?? 'unknown').'s';
    echo "\n    → Request ID: ".($r->meta['request_id'] ?? 'unknown');

    $videoData = file_get_contents($r->url);
    if ($videoData !== false) {
        saveFile('video-5s', $videoData, 'mp4');
    }
});

// ── Models & Voices ──────────────────────────────────────────────────────────

echo "\n\n── Provider Interrogation";

test('models list returns known models', function () {
    $models = Atlas::provider(Provider::xAI)->models();

    assert_true(count($models->models) > 0, 'Should have models, got: '.count($models->models));

    // Verify sorted
    $sorted = $models->models;
    sort($sorted);
    assert_true($models->models === $sorted, 'Models should be sorted alphabetically');
});

test('voices list returns xAI voices', function () {
    $voices = Atlas::provider(Provider::xAI)->voices();

    assert_true(count($voices->voices) === 5, 'Should have 5 voices');
    assert_true(in_array('eve', $voices->voices, true), 'Should include eve');
    assert_true(in_array('leo', $voices->voices, true), 'Should include leo');
});

test('capabilities are accurate', function () {
    $cap = Atlas::provider(Provider::xAI)->capabilities();

    assert_true($cap->supports('text'), 'Should support text');
    assert_true($cap->supports('stream'), 'Should support stream');
    assert_true($cap->supports('structured'), 'Should support structured');
    assert_true($cap->supports('image'), 'Should support image');
    assert_true(! $cap->supports('imageToText'), 'Should NOT support imageToText');
    assert_true($cap->supports('audio'), 'Should support audio');
    assert_true(! $cap->supports('audioToText'), 'Should NOT support audioToText');
    assert_true($cap->supports('video'), 'Should support video');
    assert_true(! $cap->supports('videoToText'), 'Should NOT support videoToText');
    assert_true(! $cap->supports('embed'), 'Should NOT support embed');
    assert_true(! $cap->supports('moderate'), 'Should NOT support moderate');
    assert_true($cap->supports('vision'), 'Should support vision');
    assert_true($cap->supports('toolCalling'), 'Should support toolCalling');
    assert_true($cap->supports('providerTools'), 'Should support providerTools');
    assert_true($cap->supports('models'), 'Should support models');
    assert_true($cap->supports('voices'), 'Should support voices');
});

// ── Provider Options Pass-through ────────────────────────────────────────────

echo "\n\n── Provider Options";

test('provider options pass through', function () {
    $r = Atlas::text(Provider::xAI, 'grok-3-mini')
        ->message('Say OK')
        ->withProviderOptions(['store' => false])
        ->asText();

    assert_true($r->text !== '', 'Should work with provider options');
});

// ─── Summary ─────────────────────────────────────────────────────────────────

echo "\n\n══════════════════════════════════════════════";
echo "\n  Results: {$passed} passed, {$failed} failed, {$skipped} skipped";
echo "\n  Media files: {$storageDir}/";
echo "\n══════════════════════════════════════════════\n";

if ($errors !== []) {
    echo "\nFailures:\n";

    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
}

echo "\n";

exit($failed > 0 ? 1 : 0);
