<?php

declare(strict_types=1);

use Atlasphp\Atlas\Models\Services\ListModelsService;
use Atlasphp\Atlas\Models\Support\PendingModelRequest;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Prism\Prism\Enums\Provider;

beforeEach(function (): void {
    $this->cache = new CacheRepository(new ArrayStore);
    $this->service = new ListModelsService(app(HttpFactory::class), $this->cache);

    config()->set('prism.providers.openai', [
        'api_key' => 'test-key',
        'url' => 'https://api.openai.com/v1',
    ]);
    config()->set('atlas.models.cache.enabled', true);
    config()->set('atlas.models.cache.ttl', 3600);
});

test('all returns models for the provider', function (): void {
    Http::fake([
        'api.openai.com/v1/models' => Http::response([
            'data' => [['id' => 'gpt-4o']],
        ]),
    ]);

    $request = new PendingModelRequest($this->service, 'openai');

    expect($request->all())->toBe([
        ['id' => 'gpt-4o', 'name' => null],
    ]);
});

test('has returns true for supported provider', function (): void {
    $request = new PendingModelRequest($this->service, 'openai');

    expect($request->has())->toBeTrue();
});

test('has returns false for unsupported provider', function (): void {
    $request = new PendingModelRequest($this->service, 'perplexity');

    expect($request->has())->toBeFalse();
});

test('refresh bypasses cache', function (): void {
    $this->cache->put('atlas:models:openai', [['id' => 'old', 'name' => null]], 3600);

    Http::fake([
        'api.openai.com/v1/models' => Http::response([
            'data' => [['id' => 'new-model']],
        ]),
    ]);

    $request = new PendingModelRequest($this->service, 'openai');
    $models = $request->refresh();

    expect($models)->toBe([['id' => 'new-model', 'name' => null]]);
});

test('clear removes cached data', function (): void {
    $this->cache->put('atlas:models:openai', [['id' => 'cached', 'name' => null]], 3600);

    $request = new PendingModelRequest($this->service, 'openai');
    $request->clear();

    expect($this->cache->has('atlas:models:openai'))->toBeFalse();
});

test('accepts Prism Provider enum', function (): void {
    $request = new PendingModelRequest($this->service, Provider::OpenAI);

    expect($request->has())->toBeTrue();
});
