<?php

declare(strict_types=1);

/**
 * Custom Driver Integration Test
 *
 * Validates that a custom driver class receives all constructor deps
 * and can execute real API calls via the OpenAI API.
 *
 * Usage: php test-custom-driver.php
 *
 * Requires OPENAI_API_KEY in sandbox/.env
 */
$app = require __DIR__.'/bootstrap.php';

use Atlasphp\Atlas\Cache\AtlasCache;
use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Middleware\MiddlewareStack;
use Atlasphp\Atlas\Providers\Concerns\BuildsHeaders;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\Handlers\TextHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Providers\SseParser;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;

// ═══════════════════════════════════════════════════════════════════════════════
// Custom Text Handler — talks to OpenAI chat completions directly
// ═══════════════════════════════════════════════════════════════════════════════

class CustomOpenAiTextHandler implements TextHandler
{
    use BuildsHeaders;

    public function __construct(
        private readonly ProviderConfig $config,
        private readonly HttpClient $http,
    ) {}

    public function text(TextRequest $request): TextResponse
    {
        $payload = $this->buildPayload($request);

        $data = $this->http->post(
            url: $this->config->baseUrl.'/chat/completions',
            headers: $this->headers(),
            body: $payload,
            timeout: $this->config->timeout,
        );

        $choice = $data['choices'][0] ?? [];
        $usage = $data['usage'] ?? [];

        return new TextResponse(
            text: $choice['message']['content'] ?? '',
            usage: new Usage(
                inputTokens: $usage['prompt_tokens'] ?? 0,
                outputTokens: $usage['completion_tokens'] ?? 0,
            ),
            finishReason: match ($choice['finish_reason'] ?? null) {
                'stop' => FinishReason::Stop,
                'length' => FinishReason::Length,
                default => FinishReason::Stop,
            },
        );
    }

    public function stream(TextRequest $request): StreamResponse
    {
        $payload = $this->buildPayload($request);
        $payload['stream'] = true;
        $payload['stream_options'] = ['include_usage' => true];

        $rawResponse = $this->http->stream(
            url: $this->config->baseUrl.'/chat/completions',
            headers: $this->headers(),
            body: $payload,
            timeout: $this->config->timeout,
        );

        $generator = function () use ($rawResponse) {
            $inputTokens = 0;
            $outputTokens = 0;
            $lastFinishReason = null;

            foreach (SseParser::parse($rawResponse) as $event) {
                $data = $event['data'];
                $choice = $data['choices'][0] ?? null;

                if (isset($data['usage'])) {
                    $inputTokens = $data['usage']['prompt_tokens'] ?? 0;
                    $outputTokens = $data['usage']['completion_tokens'] ?? 0;
                }

                if ($choice === null || ! isset($choice['delta'])) {
                    continue;
                }

                $delta = $choice['delta'];
                $finishReason = $choice['finish_reason'] ?? null;

                if (isset($delta['content']) && $delta['content'] !== '') {
                    yield new StreamChunk(
                        type: ChunkType::Text,
                        text: $delta['content'],
                    );
                }

                if ($finishReason !== null) {
                    $lastFinishReason = match ($finishReason) {
                        'stop' => FinishReason::Stop,
                        'length' => FinishReason::Length,
                        default => FinishReason::Stop,
                    };
                }
            }

            yield new StreamChunk(
                type: ChunkType::Done,
                usage: new Usage($inputTokens, $outputTokens),
                finishReason: $lastFinishReason ?? FinishReason::Stop,
            );
        };

        return new StreamResponse($generator());
    }

    public function structured(TextRequest $request): StructuredResponse
    {
        throw new RuntimeException('Structured output not implemented in this custom handler');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(TextRequest $request): array
    {
        $messages = [];

        if ($request->instructions !== null) {
            $messages[] = ['role' => 'system', 'content' => $request->instructions];
        }

        foreach ($request->messages as $msg) {
            $messages[] = ['role' => $msg->role->value, 'content' => $msg->content()];
        }

        if ($request->message !== null) {
            $messages[] = ['role' => 'user', 'content' => $request->message];
        }

        $payload = [
            'model' => $request->model,
            'messages' => $messages,
        ];

        if ($request->maxTokens !== null) {
            $payload['max_tokens'] = $request->maxTokens;
        }

        if ($request->temperature !== null) {
            $payload['temperature'] = $request->temperature;
        }

        return $payload;
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// Custom Driver
// ═══════════════════════════════════════════════════════════════════════════════

class CustomOpenAiDriver extends Driver
{
    public function name(): string
    {
        return 'custom-openai';
    }

    public function capabilities(): ProviderCapabilities
    {
        return ProviderCapabilities::withOverrides(
            new ProviderCapabilities(text: true, stream: true),
            $this->config->capabilityOverrides,
        );
    }

    protected function textHandler(): TextHandler
    {
        return new CustomOpenAiTextHandler($this->config, $this->http);
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// Test Helpers
// ═══════════════════════════════════════════════════════════════════════════════

$passed = 0;
$failed = 0;

function test(string $name, Closure $fn): void
{
    global $passed, $failed;

    try {
        $fn();
        echo "  ✓ {$name}\n";
        $passed++;
    } catch (Throwable $e) {
        echo "  ✗ {$name}\n";
        echo "    Error: {$e->getMessage()}\n";
        if ($e->getPrevious()) {
            echo "    Caused by: {$e->getPrevious()->getMessage()}\n";
        }
        $failed++;
    }
}

function assert_true(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// Tests
// ═══════════════════════════════════════════════════════════════════════════════

echo "\n🔧 Custom Driver Integration Tests\n";
echo str_repeat('─', 50)."\n\n";

// ─── Pattern 1: Custom driver class via config ──────────────────────────────

echo "Pattern 1: Custom driver class via config\n";

$app['config']->set('atlas.providers.custom-openai', [
    'driver' => CustomOpenAiDriver::class,
    'api_key' => env('OPENAI_API_KEY'),
    'base_url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
    'capabilities' => ['text' => true, 'stream' => true],
]);

test('resolves custom driver from config', function () {
    $driver = Atlas::providers()->resolve('custom-openai');
    assert_true($driver instanceof CustomOpenAiDriver, 'Should be CustomOpenAiDriver');
    assert_true($driver->name() === 'custom-openai', 'Name should be custom-openai');
});

test('custom driver capabilities include overrides', function () {
    $driver = Atlas::providers()->resolve('custom-openai');
    $caps = $driver->capabilities();
    assert_true($caps->text === true, 'text should be true');
    assert_true($caps->stream === true, 'stream should be true');
});

test('custom driver text response from real OpenAI API', function () {
    $driver = Atlas::providers()->resolve('custom-openai');

    $request = new TextRequest(
        model: 'gpt-4o-mini',
        instructions: 'You are a helpful assistant. Respond with exactly one word.',
        message: 'Say hello.',
        messageMedia: [],
        messages: [],
        maxTokens: 10,
        temperature: 0.0,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );

    $response = $driver->text($request);

    assert_true($response instanceof TextResponse, 'Should be TextResponse');
    assert_true(strlen($response->text) > 0, 'Response text should not be empty');
    assert_true($response->finishReason === FinishReason::Stop, 'Finish reason should be Stop');
    assert_true($response->usage->inputTokens > 0, 'Should have input tokens');
    assert_true($response->usage->outputTokens > 0, 'Should have output tokens');

    echo "    Response: \"{$response->text}\" ({$response->usage->inputTokens}+{$response->usage->outputTokens} tokens)\n";
});

test('custom driver streaming response from real OpenAI API', function () {
    $driver = Atlas::providers()->resolve('custom-openai');

    $request = new TextRequest(
        model: 'gpt-4o-mini',
        instructions: 'You are a helpful assistant. Respond with exactly three words.',
        message: 'Say hello world.',
        messageMedia: [],
        messages: [],
        maxTokens: 20,
        temperature: 0.0,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );

    $response = $driver->stream($request);

    assert_true($response instanceof StreamResponse, 'Should be StreamResponse');

    $chunks = [];
    $text = '';
    $doneChunk = null;

    foreach ($response as $chunk) {
        $chunks[] = $chunk;
        if ($chunk->type === ChunkType::Text) {
            $text .= $chunk->text;
        }
        if ($chunk->type === ChunkType::Done) {
            $doneChunk = $chunk;
        }
    }

    assert_true(count($chunks) > 1, 'Should have multiple chunks');
    assert_true(strlen($text) > 0, 'Assembled text should not be empty');
    assert_true($doneChunk !== null, 'Should have a Done chunk');
    assert_true($doneChunk->finishReason === FinishReason::Stop, 'Done chunk should have Stop reason');
    assert_true($doneChunk->usage->inputTokens > 0, 'Should have input tokens');

    echo "    Streamed: \"{$text}\" ({$doneChunk->usage->inputTokens}+{$doneChunk->usage->outputTokens} tokens, ".count($chunks)." chunks)\n";
});

echo "\n";

// ─── Pattern 2: withHandler override on resolved driver ─────────────────────

echo "Pattern 2: withHandler override on existing driver\n";

test('withHandler replaces text handler on a resolved driver', function () use ($app) {
    // Register a basic driver via factory
    Atlas::providers()->register('override-test', function ($app, $config) {
        $providerConfig = ProviderConfig::fromArray($config);

        return new CustomOpenAiDriver(
            config: $providerConfig,
            http: $app->make(HttpClient::class),
            middlewareStack: $app->make(MiddlewareStack::class),
            cache: $app->make(AtlasCache::class),
        );
    });

    $app['config']->set('atlas.providers.override-test', [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
    ]);

    $baseDriver = Atlas::providers()->resolve('override-test');

    // Create a custom handler that wraps the response with a prefix
    $customHandler = new class(env('OPENAI_API_KEY'), $app->make(HttpClient::class)) implements TextHandler
    {
        use BuildsHeaders;

        private ProviderConfig $config;

        public function __construct(string $apiKey, private readonly HttpClient $http)
        {
            $this->config = new ProviderConfig(apiKey: $apiKey, baseUrl: 'https://api.openai.com/v1');
        }

        public function text(TextRequest $request): TextResponse
        {
            $inner = new CustomOpenAiTextHandler($this->config, $this->http);
            $response = $inner->text($request);

            return new TextResponse(
                text: '[CUSTOM] '.$response->text,
                usage: $response->usage,
                finishReason: $response->finishReason,
            );
        }

        public function stream(TextRequest $request): StreamResponse
        {
            throw new RuntimeException('Not implemented');
        }

        public function structured(TextRequest $request): StructuredResponse
        {
            throw new RuntimeException('Not implemented');
        }
    };

    $overriddenDriver = $baseDriver->withHandler('text', $customHandler);

    $request = new TextRequest(
        model: 'gpt-4o-mini',
        instructions: 'Respond with exactly one word.',
        message: 'Say hi.',
        messageMedia: [],
        messages: [],
        maxTokens: 10,
        temperature: 0.0,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );

    // Override driver should prefix with [CUSTOM]
    $response = $overriddenDriver->text($request);
    assert_true(str_starts_with($response->text, '[CUSTOM]'), 'Override response should start with [CUSTOM] prefix');
    echo "    Override response: \"{$response->text}\"\n";

    // Original driver should still work without prefix
    $originalResponse = $baseDriver->text($request);
    assert_true(! str_starts_with($originalResponse->text, '[CUSTOM]'), 'Original driver should NOT have [CUSTOM] prefix');
    echo "    Original response: \"{$originalResponse->text}\"\n";
});

echo "\n";

// ─── Pattern 3: Fluent API with custom driver ───────────────────────────────

echo "Pattern 3: Fluent API with custom driver\n";

test('Atlas fluent API works with custom driver', function () {
    $response = Atlas::text('custom-openai', 'gpt-4o-mini')
        ->instructions('Respond with exactly one word.')
        ->message('Say goodbye.')
        ->withMaxTokens(10)
        ->withTemperature(0.0)
        ->asText();

    assert_true($response instanceof TextResponse, 'Should be TextResponse');
    assert_true(strlen($response->text) > 0, 'Response should not be empty');
    echo "    Fluent response: \"{$response->text}\"\n";
});

test('Atlas fluent streaming works with custom driver', function () {
    $response = Atlas::text('custom-openai', 'gpt-4o-mini')
        ->instructions('Respond with exactly two words.')
        ->message('Say hello world.')
        ->withMaxTokens(10)
        ->withTemperature(0.0)
        ->asStream();

    assert_true($response instanceof StreamResponse, 'Should be StreamResponse');

    $text = '';
    foreach ($response as $chunk) {
        if ($chunk->type === ChunkType::Text) {
            $text .= $chunk->text;
        }
    }

    assert_true(strlen($text) > 0, 'Streamed text should not be empty');
    echo "    Fluent streamed: \"{$text}\"\n";
});

echo "\n";

// ─── Summary ────────────────────────────────────────────────────────────────

echo str_repeat('─', 50)."\n";
echo "Results: {$passed} passed, {$failed} failed\n\n";

exit($failed > 0 ? 1 : 0);
