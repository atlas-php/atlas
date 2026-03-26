<?php

declare(strict_types=1);

use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Providers\Cohere\CohereRerankHandler;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\RerankRequest;
use Atlasphp\Atlas\Responses\RerankResponse;
use Illuminate\Support\Facades\Http;

it('sends rerank request to /v2/rerank', function () {
    Http::fake([
        'api.cohere.com/v2/rerank' => Http::response([
            'id' => 'rerank-123',
            'results' => [
                ['index' => 1, 'relevance_score' => 0.95],
                ['index' => 0, 'relevance_score' => 0.70],
            ],
            'meta' => [
                'api_version' => ['version' => '2'],
                'billed_units' => ['search_units' => 1],
            ],
        ]),
    ]);

    $handler = new CohereRerankHandler(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.cohere.com']),
        http: app(HttpClient::class),
    );

    $request = new RerankRequest(
        model: 'rerank-v3.5',
        query: 'What is machine learning?',
        documents: ['ML is a subset of AI', 'Machine learning uses algorithms'],
        topN: 2,
    );

    $response = $handler->rerank($request);

    expect($response)->toBeInstanceOf(RerankResponse::class);
    expect($response->results)->toHaveCount(2);
    expect($response->results[0]->index)->toBe(1);
    expect($response->results[0]->score)->toBe(0.95);
    expect($response->results[1]->index)->toBe(0);
    expect($response->results[1]->score)->toBe(0.70);
    expect($response->meta['id'])->toBe('rerank-123');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.cohere.com/v2/rerank'
            && $request['model'] === 'rerank-v3.5'
            && $request['query'] === 'What is machine learning?'
            && $request['top_n'] === 2;
    });
});

it('formats structured documents as YAML', function () {
    Http::fake([
        'api.cohere.com/v2/rerank' => Http::response([
            'results' => [
                ['index' => 0, 'relevance_score' => 0.90],
            ],
        ]),
    ]);

    $handler = new CohereRerankHandler(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.cohere.com']),
        http: app(HttpClient::class),
    );

    $request = new RerankRequest(
        model: 'rerank-v3.5',
        query: 'test',
        documents: [['title' => 'Doc Title', 'text' => 'Doc content']],
    );

    $handler->rerank($request);

    Http::assertSent(function ($request) {
        $docs = $request['documents'];

        return $docs[0] === "title: Doc Title\ntext: Doc content";
    });
});

it('resolves document text from original request when not in response', function () {
    Http::fake([
        'api.cohere.com/v2/rerank' => Http::response([
            'results' => [
                ['index' => 0, 'relevance_score' => 0.90],
            ],
        ]),
    ]);

    $handler = new CohereRerankHandler(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.cohere.com']),
        http: app(HttpClient::class),
    );

    $request = new RerankRequest(
        model: 'rerank-v3.5',
        query: 'test',
        documents: ['Original document text'],
    );

    $response = $handler->rerank($request);

    expect($response->results[0]->document)->toBe('Original document text');
});

it('resolves document text from response when available', function () {
    Http::fake([
        'api.cohere.com/v2/rerank' => Http::response([
            'results' => [
                ['index' => 0, 'relevance_score' => 0.90, 'document' => ['text' => 'Response doc text']],
            ],
        ]),
    ]);

    $handler = new CohereRerankHandler(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.cohere.com']),
        http: app(HttpClient::class),
    );

    $request = new RerankRequest(
        model: 'rerank-v3.5',
        query: 'test',
        documents: ['Original text'],
    );

    $response = $handler->rerank($request);

    expect($response->results[0]->document)->toBe('Response doc text');
});

it('sends max_tokens_per_doc when set', function () {
    Http::fake([
        'api.cohere.com/v2/rerank' => Http::response([
            'results' => [],
        ]),
    ]);

    $handler = new CohereRerankHandler(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.cohere.com']),
        http: app(HttpClient::class),
    );

    $request = new RerankRequest(
        model: 'rerank-v3.5',
        query: 'test',
        documents: ['doc1'],
        maxTokensPerDoc: 512,
    );

    $handler->rerank($request);

    Http::assertSent(function ($request) {
        return $request['max_tokens_per_doc'] === 512;
    });
});

it('merges provider options into request body', function () {
    Http::fake([
        'api.cohere.com/v2/rerank' => Http::response([
            'results' => [],
        ]),
    ]);

    $handler = new CohereRerankHandler(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.cohere.com']),
        http: app(HttpClient::class),
    );

    $request = new RerankRequest(
        model: 'rerank-v3.5',
        query: 'test',
        documents: ['doc1'],
        providerOptions: ['return_documents' => true],
    );

    $handler->rerank($request);

    Http::assertSent(function ($request) {
        return $request['return_documents'] === true;
    });
});
