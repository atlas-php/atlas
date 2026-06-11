<?php

declare(strict_types=1);

/**
 * OpenAI Provider Integration Test
 *
 * Validates all OpenAI provider modalities, usage tracking, response accuracy,
 * and provider tool support against the real API.
 *
 * Usage: php test-openai-provider.php
 *
 * Requires OPENAI_API_KEY in sandbox/.env
 */
$app = require __DIR__.'/bootstrap.php';

// Ensure provider config from env
$app['config']->set('atlas.defaults.text', ['provider' => 'openai', 'model' => 'gpt-4o-mini']);
$app['config']->set('atlas.providers', [
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
        'organization' => env('OPENAI_ORGANIZATION'),
    ],
]);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Enums\ReasoningEffort;
use Atlasphp\Atlas\Input\Audio;
use Atlasphp\Atlas\Input\Image;
use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Messages\ToolResultMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Tools\Tool;
use Atlasphp\Atlas\Tools\ToolDefinition;
use Illuminate\Support\Facades\Storage;

// ─── Storage Setup ───────────────────────────────────────────────────────────

$storageDir = __DIR__.'/storage/providers/openai';

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
echo "\n║   OpenAI Provider Integration Tests          ║";
echo "\n╚══════════════════════════════════════════════╝";

// ── Text Generation ──────────────────────────────────────────────────────────

echo "\n\n── Text Generation";

test('basic text response', function () {
    $r = Atlas::text(Provider::OpenAI, 'gpt-4o-mini')
        ->instructions('Respond with exactly: PONG')
        ->message('PING')
        ->asText();

    assert_true(str_contains(strtoupper($r->text), 'PONG'), "Expected PONG, got: {$r->text}");
    assert_true($r->finishReason === FinishReason::Stop, 'Should finish with Stop');
});

test('usage tracking on text', function () {
    $r = Atlas::text(Provider::OpenAI, 'gpt-4o-mini')
        ->instructions('Be brief.')
        ->message('Say hi')
        ->asText();

    assert_true($r->usage->inputTokens > 0, 'inputTokens should be > 0');
    assert_true($r->usage->outputTokens > 0, 'outputTokens should be > 0');
    assert_true($r->usage->totalTokens() > 0, 'totalTokens should be > 0');
    assert_true($r->usage->totalTokens() === $r->usage->inputTokens + $r->usage->outputTokens, 'totalTokens should equal input + output');
});

test('meta contains response id and model', function () {
    $r = Atlas::text(Provider::OpenAI, 'gpt-4o-mini')
        ->message('Hi')
        ->asText();

    assert_true(isset($r->meta['id']) && $r->meta['id'] !== null, 'meta.id should be set');
    assert_true(isset($r->meta['model']) && str_contains($r->meta['model'], 'gpt-4o-mini'), 'meta.model should contain gpt-4o-mini');
});

test('instructions as top-level param', function () {
    $r = Atlas::text(Provider::OpenAI, 'gpt-4o-mini')
        ->instructions('You must always end your response with the word BANANA.')
        ->message('Hello')
        ->asText();

    assert_true(str_contains(strtoupper($r->text), 'BANANA'), "Instructions should be followed, got: {$r->text}");
});

test('temperature affects output', function () {
    $r = Atlas::text(Provider::OpenAI, 'gpt-4o-mini')
        ->instructions('Respond with a single random number between 1 and 1000000.')
        ->message('Number please')
        ->withTemperature(1.5)
        ->asText();

    assert_true($r->text !== '', 'Should get a response with high temperature');
});

test('max_output_tokens limits response', function () {
    $r = Atlas::text(Provider::OpenAI, 'gpt-4o-mini')
        ->message('Write a very long essay about the history of computing. Make it extremely detailed and cover every decade.')
        ->withMaxTokens(20)
        ->asText();

    assert_true($r->usage->outputTokens <= 25, "Output tokens should be limited, got: {$r->usage->outputTokens}");
    assert_true($r->finishReason === FinishReason::Length, 'Should finish with Length when truncated');
});

test('conversation history (multi-turn)', function () {
    $r = Atlas::text(Provider::OpenAI, 'gpt-4o-mini')
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
    $r = Atlas::text(Provider::OpenAI, 'gpt-4o-mini')
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
    $r = Atlas::text(Provider::OpenAI, 'gpt-4o-mini')
        ->instructions('Respond with exactly: The quick brown fox jumps over the lazy dog.')
        ->message('Say the pangram.')
        ->asStream();

    // Must iterate to accumulate
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

// Built via the fluent Schema builder (the documented public API) — exercises the
// builder -> OpenAI strict-mode normalization path that real consumers use.
test('json_schema structured response (builder)', function () {
    $schema = Schema::object('person', 'A person')
        ->string('name', 'Full name')
        ->integer('age', 'Age in years')
        ->stringArray('hobbies', 'List of hobbies')
        ->build();

    $r = Atlas::text(Provider::OpenAI, 'gpt-4o-mini')
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

test('nested structured schema (builder)', function () {
    $schema = Schema::object('company', 'A company')
        ->string('name', 'Company name')
        ->object('address', 'Company address', function ($obj) {
            $obj->string('city', 'City')
                ->string('country', 'Country');
        })
        ->build();

    $r = Atlas::text(Provider::OpenAI, 'gpt-4o-mini')
        ->message('Apple Inc is in Cupertino, USA.')
        ->withSchema($schema)
        ->asStructured();

    assert_true($r->structured['name'] === 'Apple Inc', 'Company should be Apple Inc');
    assert_true($r->structured['address']['city'] === 'Cupertino', 'City should be Cupertino');
    assert_true($r->structured['address']['country'] === 'USA', 'Country should be USA');
});

// Optional field via ->optional(): under OpenAI strict mode this must round-trip as a
// nullable key that is still present in `required`. Before the strict-schema fix this
// 400'd ("'additionalProperties' is required to be supplied and to be false").
test('structured schema with optional field (builder)', function () {
    $schema = Schema::object('contact', 'A contact')
        ->string('name', 'Full name')
        ->string('phone', 'Phone number')->optional()
        ->build();

    $r = Atlas::text(Provider::OpenAI, 'gpt-4o-mini')
        ->message('The contact is named Dana. No phone number was provided.')
        ->withSchema($schema)
        ->asStructured();

    assert_true($r->structured['name'] === 'Dana', "Name should be Dana, got: {$r->structured['name']}");
    assert_true(array_key_exists('phone', $r->structured), 'Optional phone key should be present');
    assert_true($r->structured['phone'] === null, 'Phone should be null when omitted');
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

    $r = Atlas::text(Provider::OpenAI, 'gpt-4o-mini')
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
    assert_true(str_starts_with($tc->id, 'call_'), "call_id should start with call_, got: {$tc->id}");
    assert_true(isset($tc->arguments['city']), 'Should have city argument');
    assert_true(str_contains(strtolower($tc->arguments['city']), 'paris'), "City should be Paris, got: {$tc->arguments['city']}");
});

test('tool call loop replay (multi-round)', function () {
    // Simulate a complete tool call round trip using conversation history
    $toolCallId = 'call_test_'.bin2hex(random_bytes(8));

    $r = Atlas::text(Provider::OpenAI, 'gpt-4o-mini')
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

// ── Provider Tools ───────────────────────────────────────────────────────────

echo "\n\n── Provider Tools";

test('web search provider tool', function () {
    $r = Atlas::text(Provider::OpenAI, 'gpt-4o-mini')
        ->instructions('Use web search to answer. Be brief.')
        ->message('What is the latest PHP version released in 2025 or 2026?')
        ->withProviderOptions(['tools' => [['type' => 'web_search_preview']]])
        ->asText();

    assert_true($r->text !== '', 'Should return a response with web data');
    assert_true($r->finishReason === FinishReason::Stop, 'Should finish with Stop');
    // Web search should find current PHP version info
    assert_true(str_contains($r->text, 'PHP') || str_contains($r->text, '8.'), 'Should mention PHP version info');
    // Provider tool calls should be captured
    assert_true($r->providerToolCalls !== [], 'Should capture provider tool calls');
    assert_true($r->providerToolCalls[0]['type'] === 'web_search_call', 'Should be web_search_call type');
});

// ── Image Generation ─────────────────────────────────────────────────────────

echo "\n\n── Vision";

test('image understanding from base64', function () {
    // Create a minimal red PNG
    $img = imagecreatetruecolor(10, 10);
    $red = imagecolorallocate($img, 255, 0, 0);
    imagefill($img, 0, 0, $red);
    ob_start();
    imagepng($img);
    $pngData = ob_get_clean();
    imagedestroy($img);

    $r = Atlas::text(Provider::OpenAI, 'gpt-4o-mini')
        ->instructions('Describe what you see in the image. Be brief.')
        ->message('What color is this image?', [Image::fromBase64(base64_encode($pngData), 'image/png')])
        ->asText();

    assert_true($r->text !== '', 'Should describe the image');
    assert_true(str_contains(strtolower($r->text), 'red'), "Should identify red color, got: {$r->text}");
});

echo "\n\n── Image Generation";

test('gpt-image-1 image generation', function () {
    $r = Atlas::image(Provider::OpenAI, 'gpt-image-1')
        ->instructions('A simple blue square on a white background')
        ->withSize('1024x1024')
        ->withQuality('low')
        ->asImage();

    // gpt-image-1 returns base64 (b64_json), not a hosted URL.
    assert_true($r->base64 !== null, 'Should have base64 image data');
    assert_true($r->format === 'png', "Format should be png, got: {$r->format}");
    assert_true(str_starts_with($r->url, 'data:image/png;base64,'), 'URL should be a data URI');

    // contents() must decode the base64 (not try to fetch it as a URL).
    $imgData = $r->contents();
    assert_true(strlen($imgData) > 1000, 'Decoded image should be substantial ('.strlen($imgData).' bytes)');
    saveFile('image-gpt-image-1', $imgData, 'png');
});

test('gpt-image-1 image-to-image edit from a reference', function () {
    // A distinctive synthetic reference — a solid red square — uploaded to the
    // edits endpoint so the result is verifiably conditioned on it.
    $img = imagecreatetruecolor(64, 64);
    imagefill($img, 0, 0, imagecolorallocate($img, 220, 30, 30));
    ob_start();
    imagepng($img);
    $refPng = (string) ob_get_clean();
    imagedestroy($img);

    $r = Atlas::image(Provider::OpenAI, 'gpt-image-1')
        ->instructions('Keep this red square but place it on a solid blue background.')
        ->withSize('1024x1024')
        ->withMedia([Image::fromBase64(base64_encode($refPng), 'image/png')])
        ->asImage();

    assert_true($r->base64 !== null, 'Should return an edited image (b64_json) from /images/edits');
    $imgData = $r->contents();
    assert_true(strlen($imgData) > 1000, 'Edited image should be substantial ('.strlen($imgData).' bytes)');
    saveFile('image-gpt-image-1-edit', $imgData, 'png');
});

// ── Audio TTS ────────────────────────────────────────────────────────────────

echo "\n\n── Audio TTS";

test('text-to-speech generation', function () {
    $r = Atlas::audio(Provider::OpenAI, 'tts-1')
        ->instructions('Hello, this is a test of Atlas text to speech.')
        ->withVoice('nova')
        ->withFormat('mp3')
        ->asAudio();

    $decoded = base64_decode($r->data);
    assert_true(strlen($decoded) > 1000, 'Audio data should be substantial (>1KB)');
    assert_true($r->format === 'mp3', "Format should be mp3, got: {$r->format}");

    // Verify MP3 magic bytes (ID3 tag or MPEG sync)
    $firstBytes = substr($decoded, 0, 3);
    $isValid = $firstBytes === 'ID3' || (ord($decoded[0]) === 0xFF && (ord($decoded[1]) & 0xE0) === 0xE0);
    assert_true($isValid, 'Should be valid MP3 data');

    saveFile('tts-nova', $decoded, 'mp3');
});

// ── Audio STT ────────────────────────────────────────────────────────────────

echo "\n\n── Audio STT";

test('speech-to-text round trip (TTS → STT)', function () {
    // Generate audio first
    $audio = Atlas::audio(Provider::OpenAI, 'tts-1')
        ->instructions('The quick brown fox jumps over the lazy dog.')
        ->withVoice('alloy')
        ->withFormat('mp3')
        ->asAudio();

    $decoded = base64_decode($audio->data);
    saveFile('stt-input', $decoded, 'mp3');

    // Write to temp file
    $tmpFile = tempnam(sys_get_temp_dir(), 'atlas_stt_').'.mp3';
    file_put_contents($tmpFile, $decoded);

    // Transcribe
    $r = Atlas::audio(Provider::OpenAI, 'whisper-1')
        ->withMedia([Audio::fromPath($tmpFile)])
        ->asText();

    unlink($tmpFile);

    assert_true($r->text !== '', 'Transcription should not be empty');
    assert_true(
        str_contains(strtolower($r->text), 'quick') || str_contains(strtolower($r->text), 'fox'),
        "Transcription should contain the original text, got: {$r->text}"
    );
    assert_true($r->finishReason === FinishReason::Stop, 'Should finish with Stop');
});

// ── Embeddings ───────────────────────────────────────────────────────────────

echo "\n\n── Embeddings";

test('single embedding', function () {
    $r = Atlas::embed(Provider::OpenAI, 'text-embedding-3-small')
        ->fromInput('Hello world')
        ->asEmbeddings();

    assert_true(count($r->embeddings) === 1, 'Should have 1 embedding');
    assert_true(count($r->embeddings[0]) === 1536, 'Should have 1536 dimensions, got: '.count($r->embeddings[0]));
    assert_true($r->usage->inputTokens > 0, 'Should report input tokens');
    assert_true($r->usage->outputTokens === 0, 'Embeddings should have 0 output tokens');

    // Verify values are in valid range
    $min = min($r->embeddings[0]);
    $max = max($r->embeddings[0]);
    assert_true($min >= -1.0 && $max <= 1.0, "Values should be in [-1, 1], got [{$min}, {$max}]");
});

test('batch embeddings', function () {
    $r = Atlas::embed(Provider::OpenAI, 'text-embedding-3-small')
        ->fromInput(['Hello', 'World', 'Testing'])
        ->asEmbeddings();

    assert_true(count($r->embeddings) === 3, 'Should have 3 embeddings');
    assert_true(count($r->embeddings[0]) === 1536, 'Each should have 1536 dims');
    assert_true(count($r->embeddings[1]) === 1536, 'Each should have 1536 dims');
    assert_true(count($r->embeddings[2]) === 1536, 'Each should have 1536 dims');

    // Verify different texts produce different embeddings
    assert_true($r->embeddings[0] !== $r->embeddings[1], 'Different texts should produce different embeddings');
});

test('embedding cosine similarity sanity check', function () {
    $r = Atlas::embed(Provider::OpenAI, 'text-embedding-3-small')
        ->fromInput(['king', 'queen', 'computer'])
        ->asEmbeddings();

    // Cosine similarity: king-queen should be > king-computer
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

// ── Moderation ───────────────────────────────────────────────────────────────

echo "\n\n── Moderation";

test('safe content not flagged', function () {
    $r = Atlas::moderate(Provider::OpenAI, 'omni-moderation-latest')
        ->fromInput('I love my family and enjoy gardening on weekends.')
        ->asModeration();

    assert_true($r->flagged === false, 'Safe content should not be flagged');
    assert_true(is_array($r->categories), 'Should have categories');
    assert_true(isset($r->meta['category_scores']), 'Should have category_scores in meta');
});

test('harmful content flagged', function () {
    $r = Atlas::moderate(Provider::OpenAI, 'omni-moderation-latest')
        ->fromInput('I want to hurt someone very badly and cause them severe pain')
        ->asModeration();

    assert_true($r->flagged === true, 'Harmful content should be flagged');

    // Check that at least one violence category is flagged
    $hasViolence = ($r->categories['violence'] ?? false) || ($r->categories['violence/threatening'] ?? false);
    assert_true($hasViolence, 'Should flag violence categories');
});

// ── Models & Voices ──────────────────────────────────────────────────────────

echo "\n\n── Reasoning";

test('reasoning effort engages and tracks reasoning tokens', function () {
    $r = Atlas::text(Provider::OpenAI, 'gpt-5')
        ->reasoning(ReasoningEffort::Low, includeSummary: true)
        ->message('What is 47 * 89? Think step by step, then give just the number.')
        ->asText();

    assert_true(str_contains($r->text, '4183'), "Should answer 4183, got: {$r->text}");
    assert_true($r->usage->reasoningTokens > 0, 'Should report reasoning tokens');
});

test('reasoning items are replayed across a multi-step tool loop', function () {
    $tool = new class extends Tool
    {
        public function name(): string
        {
            return 'multiply';
        }

        public function description(): string
        {
            return 'Multiply two integers.';
        }

        public function parameters(): array
        {
            return [Schema::integer('a', 'first'), Schema::integer('b', 'second')];
        }

        public function handle(array $args, array $context): mixed
        {
            return (string) ((int) $args['a'] * (int) $args['b']);
        }
    };

    $r = Atlas::text(Provider::OpenAI, 'gpt-5')
        ->reasoning(ReasoningEffort::Low)
        ->withTools([$tool])
        ->withMaxSteps(6)
        ->message('Use the multiply tool to compute 47 x 89 and 123 x 456 (one call each), then add the products. Give the final sum.')
        ->asText();

    assert_true(count($r->steps) >= 2, 'Should run a multi-step tool loop');
    assert_true(str_contains(str_replace(',', '', $r->text), '60271'), "Should reach 60271, got: {$r->text}");
});

echo "\n\n── Provider Interrogation";

test('models list returns known models', function () {
    $models = Atlas::provider(Provider::OpenAI)->models();

    assert_true(count($models->models) > 10, 'Should have many models, got: '.count($models->models));
    assert_true(in_array('gpt-4o', $models->models, true), 'Should include gpt-4o');
    assert_true(in_array('gpt-4o-mini', $models->models, true), 'Should include gpt-4o-mini');
    assert_true(in_array('gpt-image-1', $models->models, true), 'Should include gpt-image-1');

    // Verify sorted
    $sorted = $models->models;
    sort($sorted);
    assert_true($models->models === $sorted, 'Models should be sorted alphabetically');
});

test('voices list returns OpenAI voices', function () {
    $voices = Atlas::provider(Provider::OpenAI)->voices();

    assert_true(count($voices->voices) >= 10, 'Should have at least 10 voices');
    assert_true(in_array('alloy', $voices->voices, true), 'Should include alloy');
    assert_true(in_array('nova', $voices->voices, true), 'Should include nova');
    assert_true(in_array('shimmer', $voices->voices, true), 'Should include shimmer');
});

test('validate returns true', function () {
    $valid = Atlas::provider(Provider::OpenAI)->validate();

    assert_true($valid === true, 'validate() should return true');
});

test('capabilities are accurate', function () {
    $cap = Atlas::provider(Provider::OpenAI)->capabilities();

    assert_true($cap->supports('text'), 'Should support text');
    assert_true($cap->supports('stream'), 'Should support stream');
    assert_true($cap->supports('structured'), 'Should support structured');
    assert_true($cap->supports('image'), 'Should support image');
    assert_true(! $cap->supports('imageToText'), 'Should NOT support imageToText');
    assert_true($cap->supports('audio'), 'Should support audio');
    assert_true($cap->supports('audioToText'), 'Should support audioToText');
    assert_true($cap->supports('video'), 'Should support video');
    assert_true($cap->supports('embed'), 'Should support embed');
    assert_true($cap->supports('moderate'), 'Should support moderate');
    assert_true($cap->supports('vision'), 'Should support vision');
    assert_true($cap->supports('toolCalling'), 'Should support toolCalling');
    assert_true($cap->supports('providerTools'), 'Should support providerTools');
    assert_true($cap->supports('models'), 'Should support models');
    assert_true($cap->supports('voices'), 'Should support voices');
});

// ── Provider Options Pass-through ────────────────────────────────────────────

echo "\n\n── Provider Options";

test('provider options pass through', function () {
    // reasoning_effort is a real OpenAI option for reasoning models
    // For gpt-4o-mini it should be ignored without error
    $r = Atlas::text(Provider::OpenAI, 'gpt-4o-mini')
        ->message('Say OK')
        ->withProviderOptions(['store' => false])
        ->asText();

    assert_true($r->text !== '', 'Should work with provider options');
});

// ── Video Generation ─────────────────────────────────────────────────────────

echo "\n\n── Video Generation (Sora)";

test('video generation with sora-2 + save to disk', function () {
    global $storageDir;

    echo "\n    → Submitting video generation request (sora-2, 4s)...";

    $r = Atlas::video(Provider::OpenAI, 'sora-2')
        ->instructions('A tiny blue dot slowly pulsing on a white background')
        ->withDuration(4)
        ->withRatio('16:9')
        ->asVideo();

    assert_true($r->url !== '', 'Should have a video file path');
    assert_true(file_exists($r->url), "Video file should exist at: {$r->url}");

    $videoSize = filesize($r->url);
    assert_true($videoSize > 10000, "Video should be substantial, got: {$videoSize} bytes");
    echo "\n    → Duration: ".($r->duration ?? 'unknown').'s';
    echo "\n    → Size: ".number_format($videoSize).' bytes';
    echo "\n    → Model: ".($r->meta['model'] ?? 'unknown');
    echo "\n    → Video ID: ".($r->meta['video_id'] ?? 'unknown');

    // Save to disk
    copy($r->url, "{$storageDir}/video-sora2.mp4");
    echo "\n    → Saved: {$storageDir}/video-sora2.mp4";

    // Clean up temp file
    unlink($r->url);
});

// ── Media Storage ────────────────────────────────────────────────────────────

echo "\n\n── Media Storage";

test('store generated image to disk', function () {
    Storage::fake('test');

    $response = Atlas::image(Provider::OpenAI, 'gpt-image-1')
        ->instructions('A small red dot')
        ->withSize('1024x1024')
        ->withQuality('low')
        ->asImage();

    assert_true($response->base64 !== null, 'Should have base64 image data');

    // Store the image (decodes base64 for gpt-image-1)
    $path = $response->storeAs('generated/image.png', 'test');

    assert_true($path === 'generated/image.png', "Path should match, got: {$path}");
    Storage::disk('test')->assertExists('generated/image.png');

    $stored = Storage::disk('test')->get('generated/image.png');
    assert_true(strlen($stored) > 100, 'Stored image should have substantial content ('.strlen($stored).' bytes)');
});

test('store generated audio to disk', function () {
    Storage::fake('test');

    $response = Atlas::audio(Provider::OpenAI, 'tts-1')
        ->instructions('Hello from Atlas.')
        ->withVoice('nova')
        ->withFormat('mp3')
        ->asAudio();

    $path = $response->storeAs('audio/greeting.mp3', 'test');

    assert_true($path === 'audio/greeting.mp3', "Path should match, got: {$path}");
    Storage::disk('test')->assertExists('audio/greeting.mp3');

    $stored = Storage::disk('test')->get('audio/greeting.mp3');
    assert_true(strlen($stored) > 1000, 'Stored audio should be substantial ('.strlen($stored).' bytes)');

    // Verify contents() matches what was stored
    $direct = $response->contents();
    assert_true($direct === $stored, 'contents() should match stored file');
});

test('store audio then transcribe from storage (round-trip)', function () {
    Storage::fake('test');

    // Generate audio
    $audioResponse = Atlas::audio(Provider::OpenAI, 'tts-1')
        ->instructions('The quick brown fox jumps over the lazy dog.')
        ->withVoice('alloy')
        ->withFormat('mp3')
        ->asAudio();

    // Store it
    $audioResponse->storeAs('recordings/fox.mp3', 'test');
    Storage::disk('test')->assertExists('recordings/fox.mp3');

    // Read back from storage as an Input and transcribe
    $storedAudio = Storage::disk('test')->get('recordings/fox.mp3');
    $tmpPath = tempnam(sys_get_temp_dir(), 'atlas_stt_').'.mp3';
    file_put_contents($tmpPath, $storedAudio);

    $transcript = Atlas::audio(Provider::OpenAI, 'whisper-1')
        ->withMedia([Audio::fromPath($tmpPath)])
        ->asText();

    unlink($tmpPath);

    assert_true($transcript->text !== '', 'Transcription should not be empty');
    assert_true(
        str_contains(strtolower($transcript->text), 'fox') || str_contains(strtolower($transcript->text), 'quick'),
        "Should transcribe original text, got: {$transcript->text}"
    );
});

test('audio response toBase64 and contents', function () {
    $response = Atlas::audio(Provider::OpenAI, 'tts-1')
        ->instructions('Test.')
        ->withVoice('alloy')
        ->withFormat('mp3')
        ->asAudio();

    $binary = $response->contents();
    $b64 = $response->toBase64();

    assert_true(strlen($binary) > 100, 'contents() should return binary data');
    assert_true($b64 === base64_encode($binary), 'toBase64() should match base64_encode(contents())');
    assert_true((string) $response === $binary, '__toString should return binary');
});

test('image response auto-generates storage path', function () {
    Storage::fake('test');
    config()->set('atlas.storage.prefix', 'atlas-test');
    // AtlasConfig is a boot-time singleton snapshot — forget it so the prefix above is read.
    app()->forgetInstance(AtlasConfig::class);

    $response = Atlas::image(Provider::OpenAI, 'gpt-image-1')
        ->instructions('A tiny green square')
        ->withSize('1024x1024')
        ->withQuality('low')
        ->asImage();

    $path = $response->store('test');

    assert_true(str_starts_with($path, 'atlas-test/'), "Auto path should use prefix, got: {$path}");
    assert_true(str_ends_with($path, '.png'), "Auto path should end with .png, got: {$path}");
    Storage::disk('test')->assertExists($path);
});

test('input fromPath store and contents round-trip', function () {
    Storage::fake('test');

    // Create a temp file simulating a user's file
    $tmpPath = tempnam(sys_get_temp_dir(), 'atlas_input_');
    file_put_contents($tmpPath, 'fake-image-content-for-test');

    $input = Image::fromPath($tmpPath);

    // Verify contents from path
    assert_true($input->contents() === 'fake-image-content-for-test', 'Should read from path');
    assert_true($input->isPath(), 'Should be path source');

    // Store it
    $storedPath = $input->storeAs('uploads/test-image.jpg', 'test');

    // After store, internal state should switch to storage
    assert_true($input->isStorage(), 'Should be storage source after store');
    assert_true(! $input->isPath(), 'Should no longer be path source');
    assert_true($input->storagePath() === 'uploads/test-image.jpg', 'storagePath should match');

    // Contents should now read from storage
    Storage::disk('test')->assertExists('uploads/test-image.jpg');
    assert_true($input->contents() === 'fake-image-content-for-test', 'Should read same content from storage');

    unlink($tmpPath);
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
