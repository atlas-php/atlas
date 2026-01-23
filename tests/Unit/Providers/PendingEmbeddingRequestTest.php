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

test('generate calls service with empty options when no config', function () {
    $embedding = [0.1, 0.2, 0.3];

    $this->embeddingService
        ->shouldReceive('generate')
        ->once()
        ->with('Hello', [], null)
        ->andReturn($embedding);

    $result = $this->request->generate('Hello');

    expect($result)->toBe($embedding);
});

test('generate calls service with metadata', function () {
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

test('generate calls service with retry config', function () {
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

test('generateBatch calls service with empty options when no config', function () {
    $embeddings = [[0.1, 0.2], [0.3, 0.4]];

    $this->embeddingService
        ->shouldReceive('generateBatch')
        ->once()
        ->with(['Hello', 'World'], [], null)
        ->andReturn($embeddings);

    $result = $this->request->generateBatch(['Hello', 'World']);

    expect($result)->toBe($embeddings);
});

test('generateBatch calls service with metadata', function () {
    $embeddings = [[0.1, 0.2], [0.3, 0.4]];
    $metadata = ['user_id' => 123];

    $this->embeddingService
        ->shouldReceive('generateBatch')
        ->once()
        ->with(['Hello', 'World'], ['metadata' => $metadata], null)
        ->andReturn($embeddings);

    $result = $this->request->withMetadata($metadata)->generateBatch(['Hello', 'World']);

    expect($result)->toBe($embeddings);
});

test('generateBatch calls service with retry config', function () {
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

    $result = $this->request->withRetry(3, 1000)->generateBatch(['Hello', 'World']);

    expect($result)->toBe($embeddings);
});

test('chaining preserves all config', function () {
    $embedding = [0.1, 0.2, 0.3];
    $metadata = ['user_id' => 123];

    $this->embeddingService
        ->shouldReceive('generate')
        ->once()
        ->withArgs(function ($text, $options, $retry) use ($metadata) {
            return $text === 'Hello'
                && $options === ['metadata' => $metadata]
                && $retry !== null
                && $retry[0] === 3
                && $retry[1] === 1000;
        })
        ->andReturn($embedding);

    $result = $this->request
        ->withMetadata($metadata)
        ->withRetry(3, 1000)
        ->generate('Hello');

    expect($result)->toBe($embedding);
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
