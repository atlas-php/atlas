<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\HttpClient;
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
