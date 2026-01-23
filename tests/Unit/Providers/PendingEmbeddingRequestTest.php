<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Services\EmbeddingService;
use Atlasphp\Atlas\Providers\Support\PendingEmbeddingRequest;

beforeEach(function () {
    $this->embeddingService = Mockery::mock(EmbeddingService::class);

    $this->request = new PendingEmbeddingRequest($this->embeddingService);
});

afterEach(function () {
    Mockery::close();
});

test('withMetadata returns new instance with metadata', function () {
    $metadata = ['user_id' => 123];

    $result = $this->request->withMetadata($metadata);

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingEmbeddingRequest::class);
});

test('withRetry returns new instance with retry config', function () {
    $result = $this->request->withRetry(3, 1000);

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingEmbeddingRequest::class);
});

test('withProvider returns new instance with provider', function () {
    $result = $this->request->withProvider('openai');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingEmbeddingRequest::class);
});

test('withProvider with model returns new instance with both', function () {
    $result = $this->request->withProvider('openai', 'text-embedding-3-large');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingEmbeddingRequest::class);
});

test('withModel returns new instance with model', function () {
    $result = $this->request->withModel('text-embedding-3-large');

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingEmbeddingRequest::class);
});

test('withProviderOptions returns new instance with options', function () {
    $result = $this->request->withProviderOptions(['dimensions' => 256]);

    expect($result)->not->toBe($this->request);
    expect($result)->toBeInstanceOf(PendingEmbeddingRequest::class);
});

test('generate with string calls service generate', function () {
    $embedding = [0.1, 0.2, 0.3];

    $this->embeddingService
        ->shouldReceive('generate')
        ->once()
        ->with('Hello', [], null)
        ->andReturn($embedding);

    $result = $this->request->generate('Hello');

    expect($result)->toBe($embedding);
});

test('generate with array calls service generateBatch', function () {
    $embeddings = [[0.1, 0.2], [0.3, 0.4]];

    $this->embeddingService
        ->shouldReceive('generateBatch')
        ->once()
        ->with(['Hello', 'World'], [], null)
        ->andReturn($embeddings);

    $result = $this->request->generate(['Hello', 'World']);

    expect($result)->toBe($embeddings);
});

test('generate passes provider to service options', function () {
    $embedding = [0.1, 0.2, 0.3];

    $this->embeddingService
        ->shouldReceive('generate')
        ->once()
        ->with('Hello', Mockery::on(function ($options) {
            return $options['provider'] === 'anthropic';
        }), null)
        ->andReturn($embedding);

    $result = $this->request->withProvider('anthropic')->generate('Hello');

    expect($result)->toBe($embedding);
});

test('generate passes model to service options', function () {
    $embedding = [0.1, 0.2, 0.3];

    $this->embeddingService
        ->shouldReceive('generate')
        ->once()
        ->with('Hello', Mockery::on(function ($options) {
            return $options['model'] === 'text-embedding-3-large';
        }), null)
        ->andReturn($embedding);

    $result = $this->request->withModel('text-embedding-3-large')->generate('Hello');

    expect($result)->toBe($embedding);
});

test('generate passes provider and model together to service options', function () {
    $embedding = [0.1, 0.2, 0.3];

    $this->embeddingService
        ->shouldReceive('generate')
        ->once()
        ->with('Hello', Mockery::on(function ($options) {
            return $options['provider'] === 'openai' && $options['model'] === 'text-embedding-3-large';
        }), null)
        ->andReturn($embedding);

    $result = $this->request->withProvider('openai', 'text-embedding-3-large')->generate('Hello');

    expect($result)->toBe($embedding);
});

test('generate with string and metadata', function () {
    $embedding = [0.1, 0.2, 0.3];
    $metadata = ['user_id' => 123];

    $this->embeddingService
        ->shouldReceive('generate')
        ->once()
        ->with('Hello', ['metadata' => $metadata], null)
        ->andReturn($embedding);

    $result = $this->request->withMetadata($metadata)->generate('Hello');

    expect($result)->toBe($embedding);
});

test('generate with array and metadata', function () {
    $embeddings = [[0.1, 0.2], [0.3, 0.4]];
    $metadata = ['user_id' => 123];

    $this->embeddingService
        ->shouldReceive('generateBatch')
        ->once()
        ->with(['Hello', 'World'], ['metadata' => $metadata], null)
        ->andReturn($embeddings);

    $result = $this->request->withMetadata($metadata)->generate(['Hello', 'World']);

    expect($result)->toBe($embeddings);
});

test('generate with string and provider options', function () {
    $embedding = [0.1, 0.2, 0.3];
    $providerOptions = ['dimensions' => 256];

    $this->embeddingService
        ->shouldReceive('generate')
        ->once()
        ->with('Hello', ['provider_options' => $providerOptions], null)
        ->andReturn($embedding);

    $result = $this->request->withProviderOptions($providerOptions)->generate('Hello');

    expect($result)->toBe($embedding);
});

test('generate with array and provider options', function () {
    $embeddings = [[0.1, 0.2], [0.3, 0.4]];
    $providerOptions = ['dimensions' => 256];

    $this->embeddingService
        ->shouldReceive('generateBatch')
        ->once()
        ->with(['Hello', 'World'], ['provider_options' => $providerOptions], null)
        ->andReturn($embeddings);

    $result = $this->request->withProviderOptions($providerOptions)->generate(['Hello', 'World']);

    expect($result)->toBe($embeddings);
});

test('generate with string and retry', function () {
    $embedding = [0.1, 0.2, 0.3];

    $this->embeddingService
        ->shouldReceive('generate')
        ->once()
        ->withArgs(function ($text, $options, $retry) {
            return $text === 'Hello'
                && $options === []
                && $retry !== null
                && $retry[0] === 3
                && $retry[1] === 1000;
        })
        ->andReturn($embedding);

    $result = $this->request->withRetry(3, 1000)->generate('Hello');

    expect($result)->toBe($embedding);
});

test('generate with array and retry', function () {
    $embeddings = [[0.1, 0.2], [0.3, 0.4]];

    $this->embeddingService
        ->shouldReceive('generateBatch')
        ->once()
        ->withArgs(function ($texts, $options, $retry) {
            return $texts === ['Hello', 'World']
                && $options === []
                && $retry !== null
                && $retry[0] === 3
                && $retry[1] === 1000;
        })
        ->andReturn($embeddings);

    $result = $this->request->withRetry(3, 1000)->generate(['Hello', 'World']);

    expect($result)->toBe($embeddings);
});

test('chaining preserves config for string', function () {
    $embedding = [0.1, 0.2, 0.3];
    $metadata = ['user_id' => 123];
    $providerOptions = ['dimensions' => 256];

    $this->embeddingService
        ->shouldReceive('generate')
        ->once()
        ->withArgs(function ($text, $options, $retry) use ($metadata, $providerOptions) {
            return $text === 'Hello'
                && $options['provider'] === 'openai'
                && $options['model'] === 'text-embedding-3-large'
                && $options['metadata'] === $metadata
                && $options['provider_options'] === $providerOptions
                && $retry !== null
                && $retry[0] === 3
                && $retry[1] === 1000;
        })
        ->andReturn($embedding);

    $result = $this->request
        ->withProvider('openai', 'text-embedding-3-large')
        ->withMetadata($metadata)
        ->withProviderOptions($providerOptions)
        ->withRetry(3, 1000)
        ->generate('Hello');

    expect($result)->toBe($embedding);
});

test('chaining preserves config for array', function () {
    $embeddings = [[0.1, 0.2], [0.3, 0.4]];
    $metadata = ['user_id' => 123];
    $providerOptions = ['dimensions' => 256];

    $this->embeddingService
        ->shouldReceive('generateBatch')
        ->once()
        ->withArgs(function ($texts, $options, $retry) use ($metadata, $providerOptions) {
            return $texts === ['Hello', 'World']
                && $options['provider'] === 'openai'
                && $options['model'] === 'text-embedding-3-large'
                && $options['metadata'] === $metadata
                && $options['provider_options'] === $providerOptions
                && $retry !== null
                && $retry[0] === 3
                && $retry[1] === 1000;
        })
        ->andReturn($embeddings);

    $result = $this->request
        ->withProvider('openai', 'text-embedding-3-large')
        ->withMetadata($metadata)
        ->withProviderOptions($providerOptions)
        ->withRetry(3, 1000)
        ->generate(['Hello', 'World']);

    expect($result)->toBe($embeddings);
});

test('withRetry with array of delays', function () {
    $embedding = [0.1, 0.2, 0.3];

    $this->embeddingService
        ->shouldReceive('generate')
        ->once()
        ->withArgs(function ($text, $options, $retry) {
            return $text === 'Hello'
                && $retry !== null
                && $retry[0] === [100, 200, 300];
        })
        ->andReturn($embedding);

    $result = $this->request->withRetry([100, 200, 300])->generate('Hello');

    expect($result)->toBe($embedding);
});

test('dimensions returns service dimensions', function () {
    $this->embeddingService
        ->shouldReceive('dimensions')
        ->once()
        ->andReturn(1536);

    $result = $this->request->dimensions();

    expect($result)->toBe(1536);
});
