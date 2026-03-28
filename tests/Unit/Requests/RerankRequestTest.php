<?php

declare(strict_types=1);

use Atlasphp\Atlas\Requests\RerankRequest;

it('constructs with required fields', function () {
    $request = new RerankRequest(
        model: 'rerank-v3.5',
        query: 'What is machine learning?',
        documents: ['doc1', 'doc2', 'doc3'],
    );

    expect($request->model)->toBe('rerank-v3.5');
    expect($request->query)->toBe('What is machine learning?');
    expect($request->documents)->toBe(['doc1', 'doc2', 'doc3']);
    expect($request->topN)->toBeNull();
    expect($request->maxTokensPerDoc)->toBeNull();
    expect($request->providerOptions)->toBe([]);
    expect($request->middleware)->toBe([]);
    expect($request->meta)->toBe([]);
});

it('constructs with all optional fields', function () {
    $request = new RerankRequest(
        model: 'rerank-v3.5',
        query: 'query',
        documents: ['doc1'],
        topN: 5,
        maxTokensPerDoc: 512,
        providerOptions: ['return_documents' => true],
        middleware: ['middleware1'],
        meta: ['key' => 'value'],
    );

    expect($request->topN)->toBe(5);
    expect($request->maxTokensPerDoc)->toBe(512);
    expect($request->providerOptions)->toBe(['return_documents' => true]);
    expect($request->middleware)->toBe(['middleware1']);
    expect($request->meta)->toBe(['key' => 'value']);
});
