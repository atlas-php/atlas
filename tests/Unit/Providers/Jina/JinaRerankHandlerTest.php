<?php

declare(strict_types=1);

use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\Jina\JinaRerankHandler;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\RerankRequest;
use Atlasphp\Atlas\Responses\RerankResponse;
use Illuminate\Support\Facades\Http;

it('sends rerank request to /v1/rerank', function () {
    Http::fake([
        'api.jina.ai/v1/rerank' => Http::response([
            'results' => [
                ['index' => 2, 'relevance_score' => 0.98, 'document' => ['text' => 'Third doc']],
                ['index' => 0, 'relevance_score' => 0.75, 'document' => ['text' => 'First doc']],
            ],
            'usage' => ['total_tokens' => 100],
        ]),
    ]);

    $handler = new JinaRerankHandler(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.jina.ai']),
        http: app(HttpClient::class),
    );

    $request = new RerankRequest(
        model: 'jina-reranker-v2-base-multilingual',
        query: 'What is deep learning?',
        documents: ['First doc', 'Second doc', 'Third doc'],
        topN: 2,
    );

    $response = $handler->rerank($request);

    expect($response)->toBeInstanceOf(RerankResponse::class);
    expect($response->results)->toHaveCount(2);
    expect($response->results[0]->index)->toBe(2);
    expect($response->results[0]->score)->toBe(0.98);
    expect($response->results[0]->document)->toBe('Third doc');
    expect($response->meta['usage'])->toBe(['total_tokens' => 100]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.jina.ai/v1/rerank'
            && $request['model'] === 'jina-reranker-v2-base-multilingual'
            && $request['query'] === 'What is deep learning?'
            && $request['top_n'] === 2;
    });
});

it('resolves document text from original request when not in response', function () {
    Http::fake([
        'api.jina.ai/v1/rerank' => Http::response([
            'results' => [
                ['index' => 0, 'relevance_score' => 0.90],
            ],
        ]),
    ]);

    $handler = new JinaRerankHandler(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.jina.ai']),
        http: app(HttpClient::class),
    );

    $request = new RerankRequest(
        model: 'jina-reranker-v2-base-multilingual',
        query: 'test',
        documents: ['Original document text'],
    );

    $response = $handler->rerank($request);

    expect($response->results[0]->document)->toBe('Original document text');
});

it('merges provider options into request body', function () {
    Http::fake([
        'api.jina.ai/v1/rerank' => Http::response([
            'results' => [],
        ]),
    ]);

    $handler = new JinaRerankHandler(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.jina.ai']),
        http: app(HttpClient::class),
    );

    $request = new RerankRequest(
        model: 'jina-reranker-v2-base-multilingual',
        query: 'test',
        documents: ['doc1'],
        providerOptions: ['return_documents' => true],
    );

    $handler->rerank($request);

    Http::assertSent(function ($request) {
        return $request['return_documents'] === true;
    });
});

it('does not send top_n when null', function () {
    Http::fake([
        'api.jina.ai/v1/rerank' => Http::response([
            'results' => [],
        ]),
    ]);

    $handler = new JinaRerankHandler(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.jina.ai']),
        http: app(HttpClient::class),
    );

    $request = new RerankRequest(
        model: 'jina-reranker-v2-base-multilingual',
        query: 'test',
        documents: ['doc1'],
    );

    $handler->rerank($request);

    Http::assertSent(function ($request) {
        return ! isset($request['top_n']);
    });
});

it('formats structured documents as key-value strings', function () {
    Http::fake([
        'api.jina.ai/v1/rerank' => Http::response([
            'results' => [
                ['index' => 0, 'relevance_score' => 0.95],
                ['index' => 1, 'relevance_score' => 0.80],
            ],
        ]),
    ]);

    $handler = new JinaRerankHandler(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.jina.ai']),
        http: app(HttpClient::class),
    );

    $documents = [
        ['title' => 'Doc One', 'body' => 'First content'],
        ['title' => 'Doc Two', 'body' => 'Second content'],
    ];

    $request = new RerankRequest(
        model: 'jina-reranker-v2-base-multilingual',
        query: 'test query',
        documents: $documents,
    );

    $handler->rerank($request);

    Http::assertSent(function ($request) {
        $docs = $request['documents'];

        return $docs[0] === "title: Doc One\nbody: First content"
            && $docs[1] === "title: Doc Two\nbody: Second content";
    });
});

it('resolves document from original array when not in response', function () {
    Http::fake([
        'api.jina.ai/v1/rerank' => Http::response([
            'results' => [
                ['index' => 0, 'relevance_score' => 0.90],
            ],
        ]),
    ]);

    $handler = new JinaRerankHandler(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.jina.ai']),
        http: app(HttpClient::class),
    );

    $documents = [
        ['title' => 'My Title', 'body' => 'My Body'],
    ];

    $request = new RerankRequest(
        model: 'jina-reranker-v2-base-multilingual',
        query: 'test',
        documents: $documents,
    );

    $response = $handler->rerank($request);

    expect($response->results[0]->document)->toBe("title: My Title\nbody: My Body");
});
