<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Embedding\PrismEmbeddingProvider;
use Atlasphp\Atlas\Providers\Services\PrismBuilder;
use Prism\Prism\Embeddings\PendingRequest as EmbeddingsPendingRequest;
use Prism\Prism\Embeddings\Response as EmbeddingsResponse;
use Prism\Prism\ValueObjects\Embedding;
use Prism\Prism\ValueObjects\EmbeddingsUsage;
use Prism\Prism\ValueObjects\Meta;

beforeEach(function () {
    $this->prismBuilder = Mockery::mock(PrismBuilder::class);
});

function createEmbeddingsResponse(array $embeddings): EmbeddingsResponse
{
    $embeddingObjects = array_map(fn ($emb) => new Embedding($emb), $embeddings);

    return new EmbeddingsResponse(
        $embeddingObjects,
        new EmbeddingsUsage(0),
        new Meta('test-id', 'test-model'),
    );
}

test('it generates embedding for single text', function () {
    $expectedEmbedding = [0.1, 0.2, 0.3, 0.4, 0.5];

    $response = createEmbeddingsResponse([$expectedEmbedding]);

    $request = Mockery::mock(EmbeddingsPendingRequest::class);
    $request->shouldReceive('asEmbeddings')->once()->andReturn($response);

    $this->prismBuilder
        ->shouldReceive('forEmbeddings')
        ->with('openai', 'text-embedding-3-small', 'Hello world', [], null)
        ->once()
        ->andReturn($request);

    $provider = new PrismEmbeddingProvider(
        $this->prismBuilder,
        'openai',
        'text-embedding-3-small',
        1536,
    );

    $result = $provider->generate('Hello world');

    expect($result)->toBe($expectedEmbedding);
});

test('it generates embedding with options', function () {
    $expectedEmbedding = [0.1, 0.2, 0.3];
    $options = ['dimensions' => 256];

    $response = createEmbeddingsResponse([$expectedEmbedding]);

    $request = Mockery::mock(EmbeddingsPendingRequest::class);
    $request->shouldReceive('asEmbeddings')->once()->andReturn($response);

    $this->prismBuilder
        ->shouldReceive('forEmbeddings')
        ->with('openai', 'text-embedding-3-small', 'Hello world', $options, null)
        ->once()
        ->andReturn($request);

    $provider = new PrismEmbeddingProvider(
        $this->prismBuilder,
        'openai',
        'text-embedding-3-small',
        1536,
    );

    $result = $provider->generate('Hello world', $options);

    expect($result)->toBe($expectedEmbedding);
});

test('it returns empty array when no embeddings in response', function () {
    $response = createEmbeddingsResponse([]);

    $request = Mockery::mock(EmbeddingsPendingRequest::class);
    $request->shouldReceive('asEmbeddings')->once()->andReturn($response);

    $this->prismBuilder
        ->shouldReceive('forEmbeddings')
        ->with('openai', 'text-embedding-3-small', 'Hello world', [], null)
        ->once()
        ->andReturn($request);

    $provider = new PrismEmbeddingProvider(
        $this->prismBuilder,
        'openai',
        'text-embedding-3-small',
        1536,
    );

    $result = $provider->generate('Hello world');

    expect($result)->toBe([]);
});

test('it generates batch embeddings for multiple texts', function () {
    $expectedEmbeddings = [
        [0.1, 0.2, 0.3],
        [0.4, 0.5, 0.6],
    ];

    $response = createEmbeddingsResponse($expectedEmbeddings);

    $request = Mockery::mock(EmbeddingsPendingRequest::class);
    $request->shouldReceive('asEmbeddings')->once()->andReturn($response);

    $this->prismBuilder
        ->shouldReceive('forEmbeddings')
        ->with('openai', 'text-embedding-3-small', ['text 1', 'text 2'], [], null)
        ->once()
        ->andReturn($request);

    $provider = new PrismEmbeddingProvider(
        $this->prismBuilder,
        'openai',
        'text-embedding-3-small',
        1536,
    );

    $result = $provider->generateBatch(['text 1', 'text 2']);

    expect($result)->toBe($expectedEmbeddings);
});

test('it returns empty array for empty batch input', function () {
    $provider = new PrismEmbeddingProvider(
        $this->prismBuilder,
        'openai',
        'text-embedding-3-small',
        1536,
    );

    $result = $provider->generateBatch([]);

    expect($result)->toBe([]);
});

test('it generates batch embeddings with options', function () {
    $expectedEmbeddings = [[0.1, 0.2], [0.3, 0.4]];
    $options = ['dimensions' => 128];

    $response = createEmbeddingsResponse($expectedEmbeddings);

    $request = Mockery::mock(EmbeddingsPendingRequest::class);
    $request->shouldReceive('asEmbeddings')->once()->andReturn($response);

    $this->prismBuilder
        ->shouldReceive('forEmbeddings')
        ->with('openai', 'text-embedding-3-small', ['text 1', 'text 2'], $options, null)
        ->once()
        ->andReturn($request);

    $provider = new PrismEmbeddingProvider(
        $this->prismBuilder,
        'openai',
        'text-embedding-3-small',
        1536,
    );

    $result = $provider->generateBatch(['text 1', 'text 2'], $options);

    expect($result)->toBe($expectedEmbeddings);
});

test('it batches large inputs according to batch size', function () {
    // Create a provider with batch size of 2
    $provider = new PrismEmbeddingProvider(
        $this->prismBuilder,
        'openai',
        'text-embedding-3-small',
        1536,
        batchSize: 2,
    );

    // First batch response
    $response1 = createEmbeddingsResponse([[0.1, 0.2], [0.3, 0.4]]);
    $request1 = Mockery::mock(EmbeddingsPendingRequest::class);
    $request1->shouldReceive('asEmbeddings')->once()->andReturn($response1);

    // Second batch response
    $response2 = createEmbeddingsResponse([[0.5, 0.6]]);
    $request2 = Mockery::mock(EmbeddingsPendingRequest::class);
    $request2->shouldReceive('asEmbeddings')->once()->andReturn($response2);

    $this->prismBuilder
        ->shouldReceive('forEmbeddings')
        ->with('openai', 'text-embedding-3-small', ['text 1', 'text 2'], [], null)
        ->once()
        ->andReturn($request1);

    $this->prismBuilder
        ->shouldReceive('forEmbeddings')
        ->with('openai', 'text-embedding-3-small', ['text 3'], [], null)
        ->once()
        ->andReturn($request2);

    $result = $provider->generateBatch(['text 1', 'text 2', 'text 3']);

    expect($result)->toBe([
        [0.1, 0.2],
        [0.3, 0.4],
        [0.5, 0.6],
    ]);
});

test('it returns configured dimensions', function () {
    $provider = new PrismEmbeddingProvider(
        $this->prismBuilder,
        'openai',
        'text-embedding-3-small',
        1536,
    );

    expect($provider->dimensions())->toBe(1536);
});

test('it returns configured provider', function () {
    $provider = new PrismEmbeddingProvider(
        $this->prismBuilder,
        'openai',
        'text-embedding-3-small',
        1536,
    );

    expect($provider->provider())->toBe('openai');
});

test('it returns configured model', function () {
    $provider = new PrismEmbeddingProvider(
        $this->prismBuilder,
        'openai',
        'text-embedding-3-small',
        1536,
    );

    expect($provider->model())->toBe('text-embedding-3-small');
});

test('it works with different providers', function () {
    $expectedEmbedding = [0.1, 0.2, 0.3];

    $response = createEmbeddingsResponse([$expectedEmbedding]);

    $request = Mockery::mock(EmbeddingsPendingRequest::class);
    $request->shouldReceive('asEmbeddings')->once()->andReturn($response);

    $this->prismBuilder
        ->shouldReceive('forEmbeddings')
        ->with('anthropic', 'voyage-3', 'Hello world', [], null)
        ->once()
        ->andReturn($request);

    $provider = new PrismEmbeddingProvider(
        $this->prismBuilder,
        'anthropic',
        'voyage-3',
        1024,
    );

    $result = $provider->generate('Hello world');

    expect($result)->toBe($expectedEmbedding);
    expect($provider->provider())->toBe('anthropic');
    expect($provider->model())->toBe('voyage-3');
    expect($provider->dimensions())->toBe(1024);
});
