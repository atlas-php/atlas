<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\OpenAi\Handlers\Embed;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\EmbedRequest;
use Atlasphp\Atlas\Responses\EmbeddingsResponse;
use Illuminate\Support\Facades\Http;

it('sends embedding request to /v1/embeddings', function () {
    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response([
            'data' => [
                ['embedding' => [0.1, 0.2, 0.3], 'index' => 0],
            ],
            'usage' => ['prompt_tokens' => 5, 'total_tokens' => 5],
        ]),
    ]);

    $handler = new Embed(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.openai.com/v1']),
        http: app(HttpClient::class),
    );

    $request = new EmbedRequest(model: 'text-embedding-3-small', input: 'Hello world');

    $response = $handler->embed($request);

    expect($response)->toBeInstanceOf(EmbeddingsResponse::class);
    expect($response->embeddings)->toBe([[0.1, 0.2, 0.3]]);
    expect($response->usage->inputTokens)->toBe(5);
    expect($response->usage->outputTokens)->toBe(0);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.openai.com/v1/embeddings'
            && $request['model'] === 'text-embedding-3-small'
            && $request['input'] === 'Hello world';
    });
});

it('handles batch embedding input', function () {
    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response([
            'data' => [
                ['embedding' => [0.1, 0.2], 'index' => 0],
                ['embedding' => [0.3, 0.4], 'index' => 1],
            ],
            'usage' => ['prompt_tokens' => 10, 'total_tokens' => 10],
        ]),
    ]);

    $handler = new Embed(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.openai.com/v1']),
        http: app(HttpClient::class),
    );

    $request = new EmbedRequest(model: 'text-embedding-3-small', input: ['Hello', 'World']);

    $response = $handler->embed($request);

    expect($response->embeddings)->toHaveCount(2);
});

it('realigns a batch response by the index field, not array position', function () {
    // Provider returns the batch out of order — index 1 first, then 0, then 2.
    // The handler must hand them back in index order so the chunked pipeline,
    // which assigns vectors positionally, attaches the right vector per chunk.
    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response([
            'data' => [
                ['embedding' => [1.1, 1.1], 'index' => 1],
                ['embedding' => [0.5, 0.5], 'index' => 0],
                ['embedding' => [2.2, 2.2], 'index' => 2],
            ],
            'usage' => ['prompt_tokens' => 9, 'total_tokens' => 9],
        ]),
    ]);

    $handler = new Embed(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.openai.com/v1']),
        http: app(HttpClient::class),
    );

    $response = $handler->embed(
        new EmbedRequest(model: 'text-embedding-3-small', input: ['a', 'b', 'c'])
    );

    expect($response->embeddings)->toBe([[0.5, 0.5], [1.1, 1.1], [2.2, 2.2]]);
});

it('forwards the configured dimensions for text-embedding-3 models', function () {
    config()->set('atlas.embeddings.dimensions', 512);
    AtlasConfig::refresh();

    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response([
            'data' => [['embedding' => array_fill(0, 512, 0.1), 'index' => 0]],
            'usage' => ['prompt_tokens' => 5, 'total_tokens' => 5],
        ]),
    ]);

    $handler = new Embed(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.openai.com/v1']),
        http: app(HttpClient::class),
    );

    $handler->embed(new EmbedRequest(model: 'text-embedding-3-small', input: 'Hello'));

    Http::assertSent(fn ($request) => $request['dimensions'] === 512);
});

it('does not send dimensions for models that do not support it', function () {
    config()->set('atlas.embeddings.dimensions', 512);

    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response([
            'data' => [['embedding' => [0.1, 0.2], 'index' => 0]],
            'usage' => ['prompt_tokens' => 5, 'total_tokens' => 5],
        ]),
    ]);

    $handler = new Embed(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.openai.com/v1']),
        http: app(HttpClient::class),
    );

    $handler->embed(new EmbedRequest(model: 'text-embedding-ada-002', input: 'Hello'));

    Http::assertSent(fn ($request) => ! isset($request['dimensions']));
});

it('preserves an explicit dimensions provider option', function () {
    config()->set('atlas.embeddings.dimensions', 512);

    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response([
            'data' => [['embedding' => array_fill(0, 256, 0.1), 'index' => 0]],
            'usage' => ['prompt_tokens' => 5, 'total_tokens' => 5],
        ]),
    ]);

    $handler = new Embed(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.openai.com/v1']),
        http: app(HttpClient::class),
    );

    $handler->embed(new EmbedRequest(
        model: 'text-embedding-3-large',
        input: 'Hello',
        providerOptions: ['dimensions' => 256],
    ));

    Http::assertSent(fn ($request) => $request['dimensions'] === 256);
});
