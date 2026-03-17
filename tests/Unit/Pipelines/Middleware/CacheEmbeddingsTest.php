<?php

declare(strict_types=1);

use Atlasphp\Atlas\Pipelines\Middleware\CacheEmbeddings;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->middleware = new CacheEmbeddings;
});

test('it passes through when cache is globally disabled and no metadata override', function () {
    config(['atlas.embeddings.cache.enabled' => false]);

    $nextCalled = false;
    $data = buildEmbeddingData();

    $result = $this->middleware->handle($data, function ($d) use (&$nextCalled) {
        $nextCalled = true;
        $d['response'] = (object) ['embeddings' => [[0.1, 0.2]]];

        return $d;
    });

    expect($nextCalled)->toBeTrue();
    expect($result['response']->embeddings)->toBe([[0.1, 0.2]]);
});

test('it passes through when metadata cache is explicitly false', function () {
    config(['atlas.embeddings.cache.enabled' => true]);

    $nextCalled = false;
    $data = buildEmbeddingData(['cache' => false]);

    $result = $this->middleware->handle($data, function ($d) use (&$nextCalled) {
        $nextCalled = true;
        $d['response'] = (object) ['embeddings' => [[0.1]]];

        return $d;
    });

    expect($nextCalled)->toBeTrue();
});

test('it caches response when globally enabled', function () {
    config(['atlas.embeddings.cache.enabled' => true]);

    $request = new stdClass;
    $request->input = 'test input';
    $data = buildEmbeddingData([], $request);

    $expectedResponse = (object) ['embeddings' => [[0.5, 0.6]]];
    $expectedKey = 'atlas:embeddings:'.md5(serialize($request));

    $result = $this->middleware->handle($data, function ($d) use ($expectedResponse) {
        $d['response'] = $expectedResponse;

        return $d;
    });

    expect($result['response'])->toBe($expectedResponse);
    expect(Cache::has($expectedKey))->toBeTrue();
    expect(Cache::get($expectedKey))->toBe($expectedResponse);
});

test('it returns cached response on second call', function () {
    config(['atlas.embeddings.cache.enabled' => true]);

    $request = new stdClass;
    $request->input = 'cached input';
    $data = buildEmbeddingData([], $request);

    $apiResponse = (object) ['embeddings' => [[0.1, 0.2, 0.3]]];
    $callCount = 0;

    $next = function ($d) use ($apiResponse, &$callCount) {
        $callCount++;
        $d['response'] = $apiResponse;

        return $d;
    };

    // First call — hits API
    $this->middleware->handle($data, $next);
    expect($callCount)->toBe(1);

    // Second call — returns cached, does not call API
    $result = $this->middleware->handle($data, $next);
    expect($callCount)->toBe(1);
    expect($result['response'])->toBe($apiResponse);
});

test('it uses explicit cache key from metadata', function () {
    config(['atlas.embeddings.cache.enabled' => true]);

    $data = buildEmbeddingData(['cache_key' => 'my-custom-key']);

    $expectedResponse = (object) ['embeddings' => [[1.0]]];

    $this->middleware->handle($data, function ($d) use ($expectedResponse) {
        $d['response'] = $expectedResponse;

        return $d;
    });

    expect(Cache::has('atlas:embeddings:my-custom-key'))->toBeTrue();
    expect(Cache::get('atlas:embeddings:my-custom-key'))->toBe($expectedResponse);
});

test('it enables cache per-request via metadata when globally disabled', function () {
    config(['atlas.embeddings.cache.enabled' => false]);

    $request = new stdClass;
    $request->input = 'override input';
    $data = buildEmbeddingData(['cache' => true], $request);

    $callCount = 0;
    $next = function ($d) use (&$callCount) {
        $callCount++;
        $d['response'] = (object) ['embeddings' => [[0.9]]];

        return $d;
    };

    // First call — caches
    $this->middleware->handle($data, $next);
    expect($callCount)->toBe(1);

    // Second call — returns cached
    $this->middleware->handle($data, $next);
    expect($callCount)->toBe(1);
});

test('it uses custom ttl from metadata', function () {
    config(['atlas.embeddings.cache.enabled' => true, 'atlas.embeddings.cache.ttl' => 3600]);

    $data = buildEmbeddingData(['cache_ttl' => 60]);

    $this->middleware->handle($data, function ($d) {
        $d['response'] = (object) ['embeddings' => [[0.1]]];

        return $d;
    });

    // Verify it was cached (TTL is internal to the cache driver, we can verify it was stored)
    $expectedKey = 'atlas:embeddings:'.md5(serialize($data['request']));
    expect(Cache::has($expectedKey))->toBeTrue();
});

test('it uses custom cache store from metadata', function () {
    config([
        'atlas.embeddings.cache.enabled' => true,
        'cache.stores.custom_store' => [
            'driver' => 'array',
        ],
    ]);

    $data = buildEmbeddingData(['cache_store' => 'custom_store']);

    $expectedResponse = (object) ['embeddings' => [[0.7]]];

    $this->middleware->handle($data, function ($d) use ($expectedResponse) {
        $d['response'] = $expectedResponse;

        return $d;
    });

    // Verify it's in the custom store
    $expectedKey = 'atlas:embeddings:'.md5(serialize($data['request']));
    expect(Cache::store('custom_store')->has($expectedKey))->toBeTrue();
});

test('it short-circuits pipeline when cache hit', function () {
    config(['atlas.embeddings.cache.enabled' => true]);

    $request = new stdClass;
    $request->input = 'short-circuit test';
    $data = buildEmbeddingData([], $request);

    $cachedResponse = (object) ['embeddings' => [[0.4, 0.5]]];
    $cacheKey = 'atlas:embeddings:'.md5(serialize($request));

    // Pre-populate cache
    Cache::put($cacheKey, $cachedResponse, 3600);

    $result = $this->middleware->handle($data, function () {
        throw new RuntimeException('Next should not be called on cache hit');
    });

    // Short-circuit returns the data array directly (not via $next)
    expect($result['response'])->toBe($cachedResponse);
});

/**
 * Build embedding pipeline data for tests.
 *
 * @param  array<string, mixed>  $metadata
 */
function buildEmbeddingData(array $metadata = [], ?object $request = null): array
{
    return [
        'pipeline' => 'embeddings',
        'metadata' => $metadata,
        'request' => $request ?? new stdClass,
    ];
}
