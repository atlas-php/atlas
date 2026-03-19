<?php

declare(strict_types=1);

/**
 * Middleware Integration Test
 *
 * Validates that provider middleware fires on every modality call
 * against the real OpenAI API. Tests both global config middleware
 * and per-request middleware.
 *
 * Usage: php test-middleware.php
 *
 * Requires OPENAI_API_KEY in sandbox/.env
 */
$app = require __DIR__.'/bootstrap.php';

$app['config']->set('atlas.providers', [
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
        'organization' => env('OPENAI_ORGANIZATION'),
    ],
]);

use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Input\Audio;
use Atlasphp\Atlas\Middleware\ProviderContext;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Schema\Schema;

// ─── Middleware Logger ────────────────────────────────────────────────────────

$middlewareLog = [];

$loggerMiddleware = new class($middlewareLog)
{
    public function __construct(private array &$log) {}

    public function handle(ProviderContext $context, Closure $next): mixed
    {
        $start = microtime(true);

        $response = $next($context);

        $elapsed = round((microtime(true) - $start) * 1000);

        $entry = [
            'provider' => $context->provider,
            'model' => $context->model,
            'method' => $context->method,
            'time_ms' => $elapsed,
        ];

        // Extract usage if available (skip StreamResponse — usage requires iteration)
        if (is_object($response)
            && ! $response instanceof StreamResponse
            && property_exists($response, 'usage')
            && $response->usage !== null) {
            $entry['input_tokens'] = $response->usage->inputTokens;
            $entry['output_tokens'] = $response->usage->outputTokens;
        }

        $this->log[] = $entry;

        return $response;
    }
};

// Register as global middleware — catches everything
$app['config']->set('atlas.middleware.provider', [$loggerMiddleware]);

// ─── Test Runner ─────────────────────────────────────────────────────────────

$passed = 0;
$failed = 0;
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

function assert_true(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
}

// ─── Tests ───────────────────────────────────────────────────────────────────

echo '╔══════════════════════════════════════════════╗';
echo "\n║   Middleware Integration Tests                ║";
echo "\n╚══════════════════════════════════════════════╝";

// ── Text ─────────────────────────────────────────────────────────────────────

echo "\n\n── Text (via global middleware)";

test('text generation logged by middleware', function () use (&$middlewareLog) {
    $countBefore = count($middlewareLog);

    $r = Atlas::text(Provider::OpenAI, 'gpt-4o-mini')
        ->instructions('Respond with exactly: PONG')
        ->message('PING')
        ->asText();

    assert_true(str_contains(strtoupper($r->text), 'PONG'), "Expected PONG, got: {$r->text}");

    $countAfter = count($middlewareLog);
    assert_true($countAfter === $countBefore + 1, 'Should have 1 new log entry, got '.($countAfter - $countBefore));

    $entry = $middlewareLog[$countAfter - 1];
    assert_true($entry['method'] === 'text', "Method should be 'text', got: {$entry['method']}");
    assert_true($entry['provider'] === 'openai', "Provider should be 'openai', got: {$entry['provider']}");
    assert_true($entry['model'] === 'gpt-4o-mini', "Model should be 'gpt-4o-mini', got: {$entry['model']}");
    assert_true($entry['input_tokens'] > 0, 'Should have input tokens');
    assert_true($entry['output_tokens'] > 0, 'Should have output tokens');
    assert_true($entry['time_ms'] > 0, 'Should have elapsed time');

    echo "\n    → {$entry['method']} | {$entry['model']} | {$entry['input_tokens']}in/{$entry['output_tokens']}out | {$entry['time_ms']}ms";
});

// ── Stream ───────────────────────────────────────────────────────────────────

echo "\n\n── Stream (via global middleware)";

test('stream logged by middleware', function () use (&$middlewareLog) {
    $countBefore = count($middlewareLog);

    $r = Atlas::text(Provider::OpenAI, 'gpt-4o-mini')
        ->instructions('Be brief.')
        ->message('Say hello')
        ->asStream();

    foreach ($r as $chunk) {
        // consume
    }

    $countAfter = count($middlewareLog);
    assert_true($countAfter === $countBefore + 1, 'Should have 1 new log entry');

    $entry = $middlewareLog[$countAfter - 1];
    assert_true($entry['method'] === 'stream', "Method should be 'stream', got: {$entry['method']}");

    echo "\n    → {$entry['method']} | {$entry['model']} | {$entry['time_ms']}ms";
});

// ── Structured ───────────────────────────────────────────────────────────────

echo "\n\n── Structured (via global middleware)";

test('structured logged by middleware', function () use (&$middlewareLog) {
    $countBefore = count($middlewareLog);

    $schema = new Schema('person', 'A person', [
        'type' => 'object',
        'properties' => ['name' => ['type' => 'string']],
        'required' => ['name'],
        'additionalProperties' => false,
    ]);

    $r = Atlas::text(Provider::OpenAI, 'gpt-4o-mini')
        ->message('Person named Alice')
        ->withSchema($schema)
        ->asStructured();

    assert_true($r->structured['name'] === 'Alice', 'Name should be Alice');

    $entry = $middlewareLog[count($middlewareLog) - 1];
    assert_true($entry['method'] === 'structured', "Method should be 'structured', got: {$entry['method']}");

    echo "\n    → {$entry['method']} | {$entry['model']} | {$entry['input_tokens']}in/{$entry['output_tokens']}out | {$entry['time_ms']}ms";
});

// ── Image ────────────────────────────────────────────────────────────────────

echo "\n\n── Image (via global middleware)";

test('image generation logged by middleware', function () use (&$middlewareLog) {
    $countBefore = count($middlewareLog);

    $r = Atlas::image(Provider::OpenAI, 'dall-e-3')
        ->instructions('A tiny red dot on white background')
        ->withSize('1024x1024')
        ->asImage();

    assert_true($r->url !== '', 'Should have image URL');

    $entry = $middlewareLog[count($middlewareLog) - 1];
    assert_true($entry['method'] === 'image', "Method should be 'image', got: {$entry['method']}");
    assert_true($entry['model'] === 'dall-e-3', "Model should be 'dall-e-3'");

    echo "\n    → {$entry['method']} | {$entry['model']} | {$entry['time_ms']}ms";
});

// ── Audio TTS ────────────────────────────────────────────────────────────────

echo "\n\n── Audio TTS (via global middleware)";

test('audio TTS logged by middleware', function () use (&$middlewareLog) {
    $countBefore = count($middlewareLog);

    $r = Atlas::audio(Provider::OpenAI, 'tts-1')
        ->instructions('Hello from middleware test.')
        ->withVoice('nova')
        ->withFormat('mp3')
        ->asAudio();

    $decoded = base64_decode($r->data);
    assert_true(strlen($decoded) > 1000, 'Audio should be substantial');

    $entry = $middlewareLog[count($middlewareLog) - 1];
    assert_true($entry['method'] === 'audio', "Method should be 'audio', got: {$entry['method']}");

    echo "\n    → {$entry['method']} | {$entry['model']} | {$entry['time_ms']}ms";
});

// ── Audio STT ────────────────────────────────────────────────────────────────

echo "\n\n── Audio STT (via global middleware)";

test('audio STT logged by middleware', function () use (&$middlewareLog) {
    // Generate audio first
    $audio = Atlas::audio(Provider::OpenAI, 'tts-1')
        ->instructions('The quick brown fox.')
        ->withVoice('alloy')
        ->withFormat('mp3')
        ->asAudio();

    $tmpFile = tempnam(sys_get_temp_dir(), 'atlas_mw_').'.mp3';
    file_put_contents($tmpFile, base64_decode($audio->data));

    $countBefore = count($middlewareLog);

    $r = Atlas::audio(Provider::OpenAI, 'whisper-1')
        ->withMedia([Audio::fromPath($tmpFile)])
        ->asText();

    unlink($tmpFile);

    assert_true($r->text !== '', 'Transcription should not be empty');

    $entry = $middlewareLog[count($middlewareLog) - 1];
    assert_true($entry['method'] === 'audioToText', "Method should be 'audioToText', got: {$entry['method']}");

    echo "\n    → {$entry['method']} | {$entry['model']} | {$entry['time_ms']}ms";
});

// ── Embed ────────────────────────────────────────────────────────────────────

echo "\n\n── Embed (via global middleware)";

test('embedding logged by middleware', function () use (&$middlewareLog) {
    $countBefore = count($middlewareLog);

    $r = Atlas::embed(Provider::OpenAI, 'text-embedding-3-small')
        ->fromInput('Hello world')
        ->asEmbeddings();

    assert_true(count($r->embeddings) === 1, 'Should have 1 embedding');

    $entry = $middlewareLog[count($middlewareLog) - 1];
    assert_true($entry['method'] === 'embed', "Method should be 'embed', got: {$entry['method']}");
    assert_true($entry['input_tokens'] > 0, 'Should have input tokens');

    echo "\n    → {$entry['method']} | {$entry['model']} | {$entry['input_tokens']}in | {$entry['time_ms']}ms";
});

// ── Moderate ─────────────────────────────────────────────────────────────────

echo "\n\n── Moderate (via global middleware)";

test('moderation logged by middleware', function () use (&$middlewareLog) {
    $countBefore = count($middlewareLog);

    $r = Atlas::moderate(Provider::OpenAI, 'omni-moderation-latest')
        ->fromInput('I love gardening on weekends.')
        ->asModeration();

    assert_true($r->flagged === false, 'Should not be flagged');

    $entry = $middlewareLog[count($middlewareLog) - 1];
    assert_true($entry['method'] === 'moderate', "Method should be 'moderate', got: {$entry['method']}");

    echo "\n    → {$entry['method']} | {$entry['model']} | {$entry['time_ms']}ms";
});

// ── Per-Request Middleware ────────────────────────────────────────────────────

echo "\n\n── Per-Request Middleware (stacking)";

test('request-level middleware stacks with global', function () use (&$middlewareLog) {
    $requestLog = [];

    $requestMiddleware = new class($requestLog)
    {
        public function __construct(private array &$log) {}

        public function handle(ProviderContext $context, Closure $next): mixed
        {
            $this->log[] = "request-mw:{$context->method}";

            return $next($context);
        }
    };

    $r = Atlas::text(Provider::OpenAI, 'gpt-4o-mini')
        ->withMiddleware([$requestMiddleware])
        ->message('Say OK')
        ->asText();

    assert_true($r->text !== '', 'Should get a response');
    assert_true(count($requestLog) === 1, 'Request middleware should fire once');
    assert_true($requestLog[0] === 'request-mw:text', "Should log 'request-mw:text', got: {$requestLog[0]}");

    // Global middleware should also have fired
    $lastGlobal = $middlewareLog[count($middlewareLog) - 1];
    assert_true($lastGlobal['method'] === 'text', 'Global should also fire');

    echo "\n    → Global fired: ✓ | Request fired: ✓ | Stacking works";
});

// ── Full Log Summary ─────────────────────────────────────────────────────────

echo "\n\n── Middleware Log Summary";
echo "\n  ┌────────────────┬──────────────────────────┬────────────┬─────────────────────┐";
echo "\n  │ Method         │ Model                    │ Time (ms)  │ Tokens (in/out)     │";
echo "\n  ├────────────────┼──────────────────────────┼────────────┼─────────────────────┤";

foreach ($middlewareLog as $entry) {
    $method = str_pad($entry['method'], 14);
    $model = str_pad($entry['model'], 24);
    $time = str_pad((string) $entry['time_ms'], 10);
    $tokens = isset($entry['input_tokens'])
        ? str_pad("{$entry['input_tokens']}/{$entry['output_tokens']}", 19)
        : str_pad('n/a', 19);
    echo "\n  │ {$method} │ {$model} │ {$time} │ {$tokens} │";
}

echo "\n  └────────────────┴──────────────────────────┴────────────┴─────────────────────┘";
echo "\n  Total middleware invocations: ".count($middlewareLog);

// ─── Summary ─────────────────────────────────────────────────────────────────

echo "\n\n══════════════════════════════════════════════";
echo "\n  Results: {$passed} passed, {$failed} failed";
echo "\n══════════════════════════════════════════════\n";

if ($errors !== []) {
    echo "\nFailures:\n";

    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
}

// Verify zero-gap: all expected methods were logged
$loggedMethods = array_unique(array_column($middlewareLog, 'method'));
sort($loggedMethods);
$expected = ['audio', 'audioToText', 'embed', 'image', 'moderate', 'stream', 'structured', 'text'];
$missing = array_diff($expected, $loggedMethods);

if ($missing === []) {
    echo "\nZero-gap verification: ALL 8 modalities captured by middleware ✓\n";
} else {
    echo "\nZero-gap verification: MISSING modalities: ".implode(', ', $missing)." ✗\n";
}

echo "\n";

exit($failed > 0 ? 1 : 0);
