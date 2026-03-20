<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\Google\Handlers\Embed;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\EmbedRequest;
use Atlasphp\Atlas\Responses\EmbeddingsResponse;
use Illuminate\Support\Facades\Http;

it('sends single input to embedContent endpoint', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'embedding' => ['values' => [0.1, 0.2, 0.3]],
        ]),
    ]);

    $handler = new Embed(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://generativelanguage.googleapis.com']),
        http: app(HttpClient::class),
    );

    $request = new EmbedRequest(model: 'text-embedding-004', input: 'Hello world');

    $response = $handler->embed($request);

    expect($response)->toBeInstanceOf(EmbeddingsResponse::class);
    expect($response->embeddings)->toBe([[0.1, 0.2, 0.3]]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), ':embedContent')
            && $request['model'] === 'models/text-embedding-004'
            && $request['content']['parts'][0]['text'] === 'Hello world';
    });
});

it('sends batch input to batchEmbedContents endpoint', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'embeddings' => [
                ['values' => [0.1, 0.2]],
                ['values' => [0.3, 0.4]],
            ],
        ]),
    ]);

    $handler = new Embed(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://generativelanguage.googleapis.com']),
        http: app(HttpClient::class),
    );

    $request = new EmbedRequest(model: 'text-embedding-004', input: ['Hello', 'World']);

    $response = $handler->embed($request);

    expect($response->embeddings)->toHaveCount(2);
    expect($response->embeddings[0])->toBe([0.1, 0.2]);
    expect($response->embeddings[1])->toBe([0.3, 0.4]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), ':batchEmbedContents')
            && isset($request['requests']);
    });
});

it('passes provider options through', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'embedding' => ['values' => [0.1]],
        ]),
    ]);

    $handler = new Embed(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://generativelanguage.googleapis.com']),
        http: app(HttpClient::class),
    );

    $request = new EmbedRequest(
        model: 'text-embedding-004',
        input: 'Hello',
        providerOptions: ['outputDimensionality' => 256],
    );

    $handler->embed($request);

    Http::assertSent(function ($request) {
        return $request['outputDimensionality'] === 256;
    });
});
