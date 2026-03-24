<?php

declare(strict_types=1);

use Atlasphp\Atlas\Cache\AtlasCache;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\OpenAi\Handlers\Provider;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Illuminate\Support\Facades\Http;

function makeProviderHandler(): Provider
{
    return new Provider(
        config: ProviderConfig::fromArray(['api_key' => 'test-key', 'url' => 'https://api.openai.com/v1']),
        http: app(HttpClient::class),
        cache: app(AtlasCache::class),
    );
}

it('lists models from /v1/models', function () {
    Http::fake([
        'api.openai.com/v1/models' => Http::response([
            'data' => [
                ['id' => 'gpt-4o', 'object' => 'model'],
                ['id' => 'gpt-4o-mini', 'object' => 'model'],
                ['id' => 'dall-e-3', 'object' => 'model'],
            ],
        ]),
    ]);

    $handler = makeProviderHandler();
    $models = $handler->models();

    expect($models->models)->toContain('gpt-4o');
    expect($models->models)->toContain('gpt-4o-mini');
    expect($models->models)->toContain('dall-e-3');
});

it('returns models sorted alphabetically', function () {
    Http::fake([
        'api.openai.com/v1/models' => Http::response([
            'data' => [
                ['id' => 'zebra-model', 'object' => 'model'],
                ['id' => 'alpha-model', 'object' => 'model'],
                ['id' => 'middle-model', 'object' => 'model'],
            ],
        ]),
    ]);

    $handler = makeProviderHandler();
    $models = $handler->models();

    expect($models->models)->toBe(['alpha-model', 'middle-model', 'zebra-model']);
});

it('returns empty model list when data key is missing', function () {
    Http::fake([
        'api.openai.com/v1/models' => Http::response(['object' => 'list']),
    ]);

    $handler = makeProviderHandler();
    $models = $handler->models();

    expect($models->models)->toBe([]);
});

it('returns hardcoded voice list', function () {
    $handler = makeProviderHandler();
    $voices = $handler->voices();

    expect($voices->voices)->toContain('alloy');
    expect($voices->voices)->toContain('nova');
    expect($voices->voices)->toContain('shimmer');
    expect($voices->voices)->toContain('echo');
    expect($voices->voices)->toHaveCount(10);
});

it('validate returns true when models succeeds', function () {
    Http::fake([
        'api.openai.com/v1/models' => Http::response([
            'data' => [['id' => 'gpt-4o', 'object' => 'model']],
        ]),
    ]);

    $handler = makeProviderHandler();

    expect($handler->validate())->toBeTrue();
});

it('validate returns false when models endpoint throws', function () {
    Http::fake([
        'api.openai.com/v1/models' => Http::response('Unauthorized', 401),
    ]);

    $handler = makeProviderHandler();

    expect($handler->validate())->toBeFalse();
});

it('flushCache clears models and voices cache', function () {
    // Enable caching so remember() actually stores values
    config(['atlas.cache.ttl.models' => 3600, 'atlas.cache.ttl.voices' => 3600]);

    $callCount = 0;

    Http::fake(function () use (&$callCount) {
        $callCount++;

        return Http::response([
            'data' => [['id' => "model-call-{$callCount}", 'object' => 'model']],
        ]);
    });

    $handler = makeProviderHandler();

    // First call — warms cache
    $models = $handler->models();
    expect($models->models)->toBe(['model-call-1']);

    // Second call — should return cached value (no new HTTP call)
    $models = $handler->models();
    expect($models->models)->toBe(['model-call-1']);
    expect($callCount)->toBe(1);

    // Flush and re-fetch — should hit API again
    $handler->flushCache();
    $models = $handler->models();
    expect($models->models)->toBe(['model-call-2']);
    expect($callCount)->toBe(2);
});
