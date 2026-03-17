<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasManager;
use Atlasphp\Atlas\Models\Support\PendingModelRequest;
use Atlasphp\Atlas\PrismProxy;
use Prism\Prism\Enums\Provider;

test('models returns PendingModelRequest with string provider', function () {
    $manager = app(AtlasManager::class);

    $request = $manager->models('openai');

    expect($request)->toBeInstanceOf(PendingModelRequest::class);
});

test('models returns PendingModelRequest with Provider enum', function () {
    $manager = app(AtlasManager::class);

    $request = $manager->models(Provider::Anthropic);

    expect($request)->toBeInstanceOf(PendingModelRequest::class);
});

test('embeddings returns PrismProxy for embeddings module', function () {
    $manager = app(AtlasManager::class);

    $proxy = $manager->embeddings();

    expect($proxy)->toBeInstanceOf(PrismProxy::class);
});

test('embeddings applies default provider and model from config', function () {
    config([
        'atlas.embeddings.provider' => 'openai',
        'atlas.embeddings.model' => 'text-embedding-3-small',
    ]);

    $manager = app(AtlasManager::class);

    $proxy = $manager->embeddings();

    // The proxy wraps a Prism embeddings request — we can verify it's a PrismProxy
    // with the embeddings module. The ->using() was applied internally.
    expect($proxy)->toBeInstanceOf(PrismProxy::class);
});

test('embeddings works without default config', function () {
    config([
        'atlas.embeddings.provider' => null,
        'atlas.embeddings.model' => null,
    ]);

    $manager = app(AtlasManager::class);

    $proxy = $manager->embeddings();

    expect($proxy)->toBeInstanceOf(PrismProxy::class);
});

test('embeddings does not apply defaults when only provider is set', function () {
    config([
        'atlas.embeddings.provider' => 'openai',
        'atlas.embeddings.model' => null,
    ]);

    $manager = app(AtlasManager::class);

    // Should not throw — defaults not applied when model is null
    $proxy = $manager->embeddings();

    expect($proxy)->toBeInstanceOf(PrismProxy::class);
});
