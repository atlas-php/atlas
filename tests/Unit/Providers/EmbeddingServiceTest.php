<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Contracts\EmbeddingProviderContract;
use Atlasphp\Atlas\Providers\Services\EmbeddingService;

beforeEach(function () {
    $this->provider = Mockery::mock(EmbeddingProviderContract::class);
    $this->service = new EmbeddingService($this->provider);
});

test('it generates single embedding', function () {
    $expectedEmbedding = [0.1, 0.2, 0.3, 0.4, 0.5];

    $this->provider
        ->shouldReceive('generate')
        ->with('test text')
        ->once()
        ->andReturn($expectedEmbedding);

    $result = $this->service->generate('test text');

    expect($result)->toBe($expectedEmbedding);
});

test('it generates batch embeddings', function () {
    $expectedEmbeddings = [
        [0.1, 0.2, 0.3],
        [0.4, 0.5, 0.6],
    ];

    $this->provider
        ->shouldReceive('generateBatch')
        ->with(['text 1', 'text 2'])
        ->once()
        ->andReturn($expectedEmbeddings);

    $result = $this->service->generateBatch(['text 1', 'text 2']);

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
