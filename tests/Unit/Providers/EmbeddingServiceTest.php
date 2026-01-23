<?php

declare(strict_types=1);

use Atlasphp\Atlas\Foundation\Contracts\PipelineContract;
use Atlasphp\Atlas\Foundation\Services\PipelineRegistry;
use Atlasphp\Atlas\Foundation\Services\PipelineRunner;
use Atlasphp\Atlas\Providers\Contracts\EmbeddingProviderContract;
use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;
use Atlasphp\Atlas\Providers\Services\EmbeddingService;
use Atlasphp\Atlas\Providers\Services\ProviderConfigService;
use Illuminate\Container\Container;

beforeEach(function () {
    $this->provider = Mockery::mock(EmbeddingProviderContract::class);
    $this->provider->shouldReceive('provider')->andReturn('openai');
    $this->provider->shouldReceive('model')->andReturn('text-embedding-3-small');
    $this->container = new Container;
    $this->registry = new PipelineRegistry;
    $this->pipelineRunner = new PipelineRunner($this->registry, $this->container);
    $this->configService = Mockery::mock(ProviderConfigService::class);
    $this->configService->shouldReceive('getRetryConfig')->andReturn(null);
    $this->configService->shouldReceive('getEmbeddingConfig')->andReturn([
        'provider' => 'openai',
        'model' => 'text-embedding-3-small',
        'dimensions' => 1536,
        'batch_size' => 100,
    ]);
    $this->prismBuilder = Mockery::mock(PrismBuilderContract::class);
    $this->service = new EmbeddingService($this->provider, $this->pipelineRunner, $this->configService, $this->prismBuilder);
});

test('it generates single embedding', function () {
    $expectedEmbedding = [0.1, 0.2, 0.3, 0.4, 0.5];

    $this->provider
        ->shouldReceive('generate')
        ->with('test text', [], null)
        ->once()
        ->andReturn($expectedEmbedding);

    $result = $this->service->generate('test text');

    expect($result)->toBe($expectedEmbedding);
});

test('it generates single embedding with options', function () {
    $expectedEmbedding = [0.1, 0.2, 0.3];
    $options = ['dimensions' => 256];

    $this->provider
        ->shouldReceive('generate')
        ->with('test text', $options, null)
        ->once()
        ->andReturn($expectedEmbedding);

    $result = $this->service->generate('test text', $options);

    expect($result)->toBe($expectedEmbedding);
});

test('it generates batch embeddings', function () {
    $expectedEmbeddings = [
        [0.1, 0.2, 0.3],
        [0.4, 0.5, 0.6],
    ];

    $this->provider
        ->shouldReceive('generateBatch')
        ->with(['text 1', 'text 2'], [], null)
        ->once()
        ->andReturn($expectedEmbeddings);

    $result = $this->service->generateBatch(['text 1', 'text 2']);

    expect($result)->toBe($expectedEmbeddings);
});

test('it generates batch embeddings with options', function () {
    $expectedEmbeddings = [
        [0.1, 0.2, 0.3],
        [0.4, 0.5, 0.6],
    ];
    $options = ['dimensions' => 256];

    $this->provider
        ->shouldReceive('generateBatch')
        ->with(['text 1', 'text 2'], $options, null)
        ->once()
        ->andReturn($expectedEmbeddings);

    $result = $this->service->generateBatch(['text 1', 'text 2'], $options);

    expect($result)->toBe($expectedEmbeddings);
});

test('it returns dimensions', function () {
    $this->provider
        ->shouldReceive('dimensions')
        ->once()
        ->andReturn(1536);

    $result = $this->service->dimensions();

    expect($result)->toBe(1536);
});

test('it runs embedding.before_generate pipeline', function () {
    $this->registry->define('embedding.before_generate', 'Before generate pipeline');
    EmbeddingBeforeGenerateHandler::reset();
    $this->registry->register('embedding.before_generate', EmbeddingBeforeGenerateHandler::class);

    $expectedEmbedding = [0.1, 0.2, 0.3];
    $this->provider
        ->shouldReceive('generate')
        ->with('test text', [], null)
        ->once()
        ->andReturn($expectedEmbedding);

    $this->service->generate('test text');

    expect(EmbeddingBeforeGenerateHandler::$called)->toBeTrue();
    expect(EmbeddingBeforeGenerateHandler::$data)->not->toBeNull();
    expect(EmbeddingBeforeGenerateHandler::$data['text'])->toBe('test text');
    expect(EmbeddingBeforeGenerateHandler::$data['provider'])->toBe('openai');
    expect(EmbeddingBeforeGenerateHandler::$data['model'])->toBe('text-embedding-3-small');
    expect(EmbeddingBeforeGenerateHandler::$data['options'])->toBe([]);
});

test('it runs embedding.after_generate pipeline', function () {
    $this->registry->define('embedding.after_generate', 'After generate pipeline');
    EmbeddingAfterGenerateHandler::reset();
    $this->registry->register('embedding.after_generate', EmbeddingAfterGenerateHandler::class);

    $expectedEmbedding = [0.1, 0.2, 0.3];
    $this->provider
        ->shouldReceive('generate')
        ->with('test text', [], null)
        ->once()
        ->andReturn($expectedEmbedding);

    $this->service->generate('test text');

    expect(EmbeddingAfterGenerateHandler::$called)->toBeTrue();
    expect(EmbeddingAfterGenerateHandler::$data)->not->toBeNull();
    expect(EmbeddingAfterGenerateHandler::$data['text'])->toBe('test text');
    expect(EmbeddingAfterGenerateHandler::$data['provider'])->toBe('openai');
    expect(EmbeddingAfterGenerateHandler::$data['model'])->toBe('text-embedding-3-small');
    expect(EmbeddingAfterGenerateHandler::$data['result'])->toBe($expectedEmbedding);
    expect(EmbeddingAfterGenerateHandler::$data['options'])->toBe([]);
});

test('it allows before_generate pipeline to modify text', function () {
    $this->registry->define('embedding.before_generate', 'Before generate pipeline');
    $this->registry->register('embedding.before_generate', EmbeddingTextModifyingHandler::class);

    $expectedEmbedding = [0.1, 0.2, 0.3];
    $this->provider
        ->shouldReceive('generate')
        ->with('MODIFIED: original text', [], null)
        ->once()
        ->andReturn($expectedEmbedding);

    $result = $this->service->generate('original text');

    expect($result)->toBe($expectedEmbedding);
});

test('it runs embedding.before_generate_batch pipeline', function () {
    $this->registry->define('embedding.before_generate_batch', 'Before generate batch pipeline');
    EmbeddingBeforeGenerateBatchHandler::reset();
    $this->registry->register('embedding.before_generate_batch', EmbeddingBeforeGenerateBatchHandler::class);

    $expectedEmbeddings = [[0.1], [0.2]];
    $this->provider
        ->shouldReceive('generateBatch')
        ->with(['text 1', 'text 2'], [], null)
        ->once()
        ->andReturn($expectedEmbeddings);

    $this->service->generateBatch(['text 1', 'text 2']);

    expect(EmbeddingBeforeGenerateBatchHandler::$called)->toBeTrue();
    expect(EmbeddingBeforeGenerateBatchHandler::$data)->not->toBeNull();
    expect(EmbeddingBeforeGenerateBatchHandler::$data['texts'])->toBe(['text 1', 'text 2']);
    expect(EmbeddingBeforeGenerateBatchHandler::$data['provider'])->toBe('openai');
    expect(EmbeddingBeforeGenerateBatchHandler::$data['model'])->toBe('text-embedding-3-small');
    expect(EmbeddingBeforeGenerateBatchHandler::$data['options'])->toBe([]);
});

test('it runs embedding.after_generate_batch pipeline', function () {
    $this->registry->define('embedding.after_generate_batch', 'After generate batch pipeline');
    EmbeddingAfterGenerateBatchHandler::reset();
    $this->registry->register('embedding.after_generate_batch', EmbeddingAfterGenerateBatchHandler::class);

    $expectedEmbeddings = [[0.1], [0.2]];
    $this->provider
        ->shouldReceive('generateBatch')
        ->with(['text 1', 'text 2'], [], null)
        ->once()
        ->andReturn($expectedEmbeddings);

    $this->service->generateBatch(['text 1', 'text 2']);

    expect(EmbeddingAfterGenerateBatchHandler::$called)->toBeTrue();
    expect(EmbeddingAfterGenerateBatchHandler::$data)->not->toBeNull();
    expect(EmbeddingAfterGenerateBatchHandler::$data['texts'])->toBe(['text 1', 'text 2']);
    expect(EmbeddingAfterGenerateBatchHandler::$data['provider'])->toBe('openai');
    expect(EmbeddingAfterGenerateBatchHandler::$data['model'])->toBe('text-embedding-3-small');
    expect(EmbeddingAfterGenerateBatchHandler::$data['result'])->toBe($expectedEmbeddings);
    expect(EmbeddingAfterGenerateBatchHandler::$data['options'])->toBe([]);
});

test('it runs embedding.on_error pipeline when generate fails', function () {
    $this->registry->define('embedding.on_error', 'Error pipeline');
    EmbeddingErrorHandler::reset();
    $this->registry->register('embedding.on_error', EmbeddingErrorHandler::class);

    $this->provider
        ->shouldReceive('generate')
        ->andThrow(new \RuntimeException('API Error'));

    try {
        $this->service->generate('test text');
    } catch (\RuntimeException $e) {
        // Expected
    }

    expect(EmbeddingErrorHandler::$called)->toBeTrue();
    expect(EmbeddingErrorHandler::$data)->not->toBeNull();
    expect(EmbeddingErrorHandler::$data['operation'])->toBe('generate');
    expect(EmbeddingErrorHandler::$data['text'])->toBe('test text');
    expect(EmbeddingErrorHandler::$data['texts'])->toBeNull();
    expect(EmbeddingErrorHandler::$data['provider'])->toBe('openai');
    expect(EmbeddingErrorHandler::$data['model'])->toBe('text-embedding-3-small');
    expect(EmbeddingErrorHandler::$data['exception'])->toBeInstanceOf(\RuntimeException::class);
    expect(EmbeddingErrorHandler::$data['exception']->getMessage())->toBe('API Error');
});

test('it runs embedding.on_error pipeline when generateBatch fails', function () {
    $this->registry->define('embedding.on_error', 'Error pipeline');
    EmbeddingErrorHandler::reset();
    $this->registry->register('embedding.on_error', EmbeddingErrorHandler::class);

    $this->provider
        ->shouldReceive('generateBatch')
        ->andThrow(new \RuntimeException('Batch API Error'));

    try {
        $this->service->generateBatch(['text 1', 'text 2']);
    } catch (\RuntimeException $e) {
        // Expected
    }

    expect(EmbeddingErrorHandler::$called)->toBeTrue();
    expect(EmbeddingErrorHandler::$data)->not->toBeNull();
    expect(EmbeddingErrorHandler::$data['operation'])->toBe('generate_batch');
    expect(EmbeddingErrorHandler::$data['text'])->toBeNull();
    expect(EmbeddingErrorHandler::$data['texts'])->toBe(['text 1', 'text 2']);
    expect(EmbeddingErrorHandler::$data['provider'])->toBe('openai');
    expect(EmbeddingErrorHandler::$data['model'])->toBe('text-embedding-3-small');
    expect(EmbeddingErrorHandler::$data['exception'])->toBeInstanceOf(\RuntimeException::class);
    expect(EmbeddingErrorHandler::$data['exception']->getMessage())->toBe('Batch API Error');
});

test('it rethrows exception after running error pipeline', function () {
    $this->registry->define('embedding.on_error', 'Error pipeline');
    EmbeddingErrorHandler::reset();
    $this->registry->register('embedding.on_error', EmbeddingErrorHandler::class);

    $this->provider
        ->shouldReceive('generate')
        ->andThrow(new \RuntimeException('API Error'));

    expect(fn () => $this->service->generate('test text'))
        ->toThrow(\RuntimeException::class, 'API Error');
});

// ===========================================
// RETRY TESTS
// ===========================================

test('it passes explicit retry to provider for generate', function () {
    $expectedEmbedding = [0.1, 0.2, 0.3];
    $retryConfig = [3, 1000, null, true];

    $this->provider
        ->shouldReceive('generate')
        ->with('test text', [], $retryConfig)
        ->once()
        ->andReturn($expectedEmbedding);

    $result = $this->service->generate('test text', [], $retryConfig);

    expect($result)->toBe($expectedEmbedding);
});

test('it passes explicit retry to provider for generateBatch', function () {
    $expectedEmbeddings = [[0.1, 0.2], [0.3, 0.4]];
    $retryConfig = [3, 1000, null, true];

    $this->provider
        ->shouldReceive('generateBatch')
        ->with(['text 1', 'text 2'], [], $retryConfig)
        ->once()
        ->andReturn($expectedEmbeddings);

    $result = $this->service->generateBatch(['text 1', 'text 2'], [], $retryConfig);

    expect($result)->toBe($expectedEmbeddings);
});

test('it uses config retry when explicit retry is null', function () {
    $expectedEmbedding = [0.1, 0.2, 0.3];
    $configRetry = [2, 500, null, true];

    // Create a new instance with retry config
    $configService = Mockery::mock(ProviderConfigService::class);
    $configService->shouldReceive('getRetryConfig')->andReturn($configRetry);

    $service = new EmbeddingService($this->provider, $this->pipelineRunner, $configService, $this->prismBuilder);

    $this->provider
        ->shouldReceive('generate')
        ->with('test text', [], $configRetry)
        ->once()
        ->andReturn($expectedEmbedding);

    $result = $service->generate('test text', [], null);

    expect($result)->toBe($expectedEmbedding);
});

test('it uses null retry when both explicit and config are null', function () {
    $expectedEmbedding = [0.1, 0.2, 0.3];

    $this->provider
        ->shouldReceive('generate')
        ->with('test text', [], null)
        ->once()
        ->andReturn($expectedEmbedding);

    $result = $this->service->generate('test text', [], null);

    expect($result)->toBe($expectedEmbedding);
});

test('it passes retry with closure to provider', function () {
    $expectedEmbedding = [0.1, 0.2, 0.3];
    $sleepFn = fn ($attempt) => $attempt * 100;
    $retryConfig = [3, $sleepFn, null, true];

    $this->provider
        ->shouldReceive('generate')
        ->with('test text', [], $retryConfig)
        ->once()
        ->andReturn($expectedEmbedding);

    $result = $this->service->generate('test text', [], $retryConfig);

    expect($result)->toBe($expectedEmbedding);
});

test('it passes retry with when callback to provider', function () {
    $expectedEmbedding = [0.1, 0.2, 0.3];
    $whenCallback = fn ($e) => $e->getCode() === 429;
    $retryConfig = [3, 1000, $whenCallback, true];

    $this->provider
        ->shouldReceive('generate')
        ->with('test text', [], $retryConfig)
        ->once()
        ->andReturn($expectedEmbedding);

    $result = $this->service->generate('test text', [], $retryConfig);

    expect($result)->toBe($expectedEmbedding);
});

// ===========================================
// PROVIDER/MODEL OVERRIDE TESTS
// ===========================================

test('it uses PrismBuilder when provider override is set for generate', function () {
    $expectedEmbedding = [0.1, 0.2, 0.3];

    // Create mock embedding object
    $mockEmbedding = new stdClass;
    $mockEmbedding->embedding = $expectedEmbedding;

    // Create mock response
    $mockResponse = new stdClass;
    $mockResponse->embeddings = [$mockEmbedding];

    // Create mock request
    $mockRequest = Mockery::mock();
    $mockRequest->shouldReceive('asEmbeddings')->once()->andReturn($mockResponse);

    $this->prismBuilder
        ->shouldReceive('forEmbeddings')
        ->with('anthropic', 'text-embedding-3-small', 'test text', Mockery::type('array'), null)
        ->once()
        ->andReturn($mockRequest);

    // Provider should NOT be called when override is set
    $this->provider->shouldNotReceive('generate');

    $result = $this->service->generate('test text', ['provider' => 'anthropic']);

    expect($result)->toBe($expectedEmbedding);
});

test('it uses PrismBuilder when model override is set for generate', function () {
    $expectedEmbedding = [0.1, 0.2, 0.3];

    $mockEmbedding = new stdClass;
    $mockEmbedding->embedding = $expectedEmbedding;

    $mockResponse = new stdClass;
    $mockResponse->embeddings = [$mockEmbedding];

    $mockRequest = Mockery::mock();
    $mockRequest->shouldReceive('asEmbeddings')->once()->andReturn($mockResponse);

    $this->prismBuilder
        ->shouldReceive('forEmbeddings')
        ->with('openai', 'text-embedding-3-large', 'test text', Mockery::type('array'), null)
        ->once()
        ->andReturn($mockRequest);

    $this->provider->shouldNotReceive('generate');

    $result = $this->service->generate('test text', ['model' => 'text-embedding-3-large']);

    expect($result)->toBe($expectedEmbedding);
});

test('it uses PrismBuilder when both provider and model override are set for generate', function () {
    $expectedEmbedding = [0.1, 0.2, 0.3];

    $mockEmbedding = new stdClass;
    $mockEmbedding->embedding = $expectedEmbedding;

    $mockResponse = new stdClass;
    $mockResponse->embeddings = [$mockEmbedding];

    $mockRequest = Mockery::mock();
    $mockRequest->shouldReceive('asEmbeddings')->once()->andReturn($mockResponse);

    $this->prismBuilder
        ->shouldReceive('forEmbeddings')
        ->with('anthropic', 'voyage-3', 'test text', Mockery::type('array'), null)
        ->once()
        ->andReturn($mockRequest);

    $this->provider->shouldNotReceive('generate');

    $result = $this->service->generate('test text', ['provider' => 'anthropic', 'model' => 'voyage-3']);

    expect($result)->toBe($expectedEmbedding);
});

test('it returns empty array when response has no embeddings for generate', function () {
    $mockResponse = new stdClass;
    $mockResponse->embeddings = [];

    $mockRequest = Mockery::mock();
    $mockRequest->shouldReceive('asEmbeddings')->once()->andReturn($mockResponse);

    $this->prismBuilder
        ->shouldReceive('forEmbeddings')
        ->once()
        ->andReturn($mockRequest);

    $result = $this->service->generate('test text', ['provider' => 'anthropic']);

    expect($result)->toBe([]);
});

test('it uses PrismBuilder when provider override is set for generateBatch', function () {
    $expectedEmbeddings = [
        [0.1, 0.2, 0.3],
        [0.4, 0.5, 0.6],
    ];

    $mockEmbedding1 = new stdClass;
    $mockEmbedding1->embedding = $expectedEmbeddings[0];

    $mockEmbedding2 = new stdClass;
    $mockEmbedding2->embedding = $expectedEmbeddings[1];

    $mockResponse = new stdClass;
    $mockResponse->embeddings = [$mockEmbedding1, $mockEmbedding2];

    $mockRequest = Mockery::mock();
    $mockRequest->shouldReceive('asEmbeddings')->once()->andReturn($mockResponse);

    $this->prismBuilder
        ->shouldReceive('forEmbeddings')
        ->with('anthropic', 'text-embedding-3-small', ['text 1', 'text 2'], Mockery::type('array'), null)
        ->once()
        ->andReturn($mockRequest);

    $this->provider->shouldNotReceive('generateBatch');

    $result = $this->service->generateBatch(['text 1', 'text 2'], ['provider' => 'anthropic']);

    expect($result)->toBe($expectedEmbeddings);
});

test('it uses PrismBuilder when model override is set for generateBatch', function () {
    $expectedEmbeddings = [
        [0.1, 0.2, 0.3],
        [0.4, 0.5, 0.6],
    ];

    $mockEmbedding1 = new stdClass;
    $mockEmbedding1->embedding = $expectedEmbeddings[0];

    $mockEmbedding2 = new stdClass;
    $mockEmbedding2->embedding = $expectedEmbeddings[1];

    $mockResponse = new stdClass;
    $mockResponse->embeddings = [$mockEmbedding1, $mockEmbedding2];

    $mockRequest = Mockery::mock();
    $mockRequest->shouldReceive('asEmbeddings')->once()->andReturn($mockResponse);

    $this->prismBuilder
        ->shouldReceive('forEmbeddings')
        ->with('openai', 'text-embedding-3-large', ['text 1', 'text 2'], Mockery::type('array'), null)
        ->once()
        ->andReturn($mockRequest);

    $this->provider->shouldNotReceive('generateBatch');

    $result = $this->service->generateBatch(['text 1', 'text 2'], ['model' => 'text-embedding-3-large']);

    expect($result)->toBe($expectedEmbeddings);
});

test('it batches texts according to batch_size when using override for generateBatch', function () {
    // Configure batch_size of 2
    $configService = Mockery::mock(ProviderConfigService::class);
    $configService->shouldReceive('getRetryConfig')->andReturn(null);
    $configService->shouldReceive('getEmbeddingConfig')->andReturn([
        'provider' => 'openai',
        'model' => 'text-embedding-3-small',
        'dimensions' => 1536,
        'batch_size' => 2,
    ]);

    $service = new EmbeddingService($this->provider, $this->pipelineRunner, $configService, $this->prismBuilder);

    // First batch response
    $mockEmbedding1 = new stdClass;
    $mockEmbedding1->embedding = [0.1, 0.2];
    $mockEmbedding2 = new stdClass;
    $mockEmbedding2->embedding = [0.3, 0.4];
    $mockResponse1 = new stdClass;
    $mockResponse1->embeddings = [$mockEmbedding1, $mockEmbedding2];

    // Second batch response
    $mockEmbedding3 = new stdClass;
    $mockEmbedding3->embedding = [0.5, 0.6];
    $mockResponse2 = new stdClass;
    $mockResponse2->embeddings = [$mockEmbedding3];

    $mockRequest1 = Mockery::mock();
    $mockRequest1->shouldReceive('asEmbeddings')->once()->andReturn($mockResponse1);

    $mockRequest2 = Mockery::mock();
    $mockRequest2->shouldReceive('asEmbeddings')->once()->andReturn($mockResponse2);

    // Expect two calls to forEmbeddings - one for each batch
    $this->prismBuilder
        ->shouldReceive('forEmbeddings')
        ->with('anthropic', 'text-embedding-3-small', ['text 1', 'text 2'], Mockery::type('array'), null)
        ->once()
        ->andReturn($mockRequest1);

    $this->prismBuilder
        ->shouldReceive('forEmbeddings')
        ->with('anthropic', 'text-embedding-3-small', ['text 3'], Mockery::type('array'), null)
        ->once()
        ->andReturn($mockRequest2);

    $this->provider->shouldNotReceive('generateBatch');

    $result = $service->generateBatch(['text 1', 'text 2', 'text 3'], ['provider' => 'anthropic']);

    expect($result)->toBe([
        [0.1, 0.2],
        [0.3, 0.4],
        [0.5, 0.6],
    ]);
});

test('it passes retry config to PrismBuilder when using override for generate', function () {
    $expectedEmbedding = [0.1, 0.2, 0.3];
    $retryConfig = [3, 1000, null, true];

    $mockEmbedding = new stdClass;
    $mockEmbedding->embedding = $expectedEmbedding;

    $mockResponse = new stdClass;
    $mockResponse->embeddings = [$mockEmbedding];

    $mockRequest = Mockery::mock();
    $mockRequest->shouldReceive('asEmbeddings')->once()->andReturn($mockResponse);

    $this->prismBuilder
        ->shouldReceive('forEmbeddings')
        ->with('anthropic', 'text-embedding-3-small', 'test text', Mockery::type('array'), $retryConfig)
        ->once()
        ->andReturn($mockRequest);

    $result = $this->service->generate('test text', ['provider' => 'anthropic'], $retryConfig);

    expect($result)->toBe($expectedEmbedding);
});

test('it passes retry config to PrismBuilder when using override for generateBatch', function () {
    $expectedEmbeddings = [[0.1, 0.2], [0.3, 0.4]];
    $retryConfig = [3, 1000, null, true];

    $mockEmbedding1 = new stdClass;
    $mockEmbedding1->embedding = $expectedEmbeddings[0];
    $mockEmbedding2 = new stdClass;
    $mockEmbedding2->embedding = $expectedEmbeddings[1];

    $mockResponse = new stdClass;
    $mockResponse->embeddings = [$mockEmbedding1, $mockEmbedding2];

    $mockRequest = Mockery::mock();
    $mockRequest->shouldReceive('asEmbeddings')->once()->andReturn($mockResponse);

    $this->prismBuilder
        ->shouldReceive('forEmbeddings')
        ->with('anthropic', 'text-embedding-3-small', ['text 1', 'text 2'], Mockery::type('array'), $retryConfig)
        ->once()
        ->andReturn($mockRequest);

    $result = $this->service->generateBatch(['text 1', 'text 2'], ['provider' => 'anthropic'], $retryConfig);

    expect($result)->toBe($expectedEmbeddings);
});

// Pipeline Handler Classes for Tests

class EmbeddingBeforeGenerateHandler implements PipelineContract
{
    public static bool $called = false;

    public static ?array $data = null;

    public static function reset(): void
    {
        self::$called = false;
        self::$data = null;
    }

    public function handle(mixed $data, \Closure $next): mixed
    {
        self::$called = true;
        self::$data = $data;

        return $next($data);
    }
}

class EmbeddingAfterGenerateHandler implements PipelineContract
{
    public static bool $called = false;

    public static ?array $data = null;

    public static function reset(): void
    {
        self::$called = false;
        self::$data = null;
    }

    public function handle(mixed $data, \Closure $next): mixed
    {
        self::$called = true;
        self::$data = $data;

        return $next($data);
    }
}

class EmbeddingTextModifyingHandler implements PipelineContract
{
    public function handle(mixed $data, \Closure $next): mixed
    {
        $data['text'] = 'MODIFIED: '.$data['text'];

        return $next($data);
    }
}

class EmbeddingBeforeGenerateBatchHandler implements PipelineContract
{
    public static bool $called = false;

    public static ?array $data = null;

    public static function reset(): void
    {
        self::$called = false;
        self::$data = null;
    }

    public function handle(mixed $data, \Closure $next): mixed
    {
        self::$called = true;
        self::$data = $data;

        return $next($data);
    }
}

class EmbeddingAfterGenerateBatchHandler implements PipelineContract
{
    public static bool $called = false;

    public static ?array $data = null;

    public static function reset(): void
    {
        self::$called = false;
        self::$data = null;
    }

    public function handle(mixed $data, \Closure $next): mixed
    {
        self::$called = true;
        self::$data = $data;

        return $next($data);
    }
}

class EmbeddingErrorHandler implements PipelineContract
{
    public static bool $called = false;

    public static ?array $data = null;

    public static function reset(): void
    {
        self::$called = false;
        self::$data = null;
    }

    public function handle(mixed $data, \Closure $next): mixed
    {
        self::$called = true;
        self::$data = $data;

        return $next($data);
    }
}
