<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasCache;
use Atlasphp\Atlas\AtlasConfig;
use Illuminate\Support\Facades\Cache;

it('remembers a value by type and key', function () {
    config(['atlas.cache.ttl.embeddings' => 3600]);
    $cache = new AtlasCache(AtlasConfig::fromConfig());

    $vector = [0.1, 0.2, 0.3];
    $callCount = 0;

    $generate = function () use ($vector, &$callCount) {
        $callCount++;

        return $vector;
    };

    // First call — generates
    $result1 = $cache->remember('embeddings', 'test-key', $generate);
    expect($result1)->toBe($vector);
    expect($callCount)->toBe(1);

    // Second call — cached
    $result2 = $cache->remember('embeddings', 'test-key', $generate);
    expect($result2)->toBe($vector);
    expect($callCount)->toBe(1);
});

it('bypasses cache when ttl is zero', function () {
    config(['atlas.cache.ttl.embeddings' => 0]);
    $cache = new AtlasCache(AtlasConfig::fromConfig());

    $callCount = 0;
    $generate = function () use (&$callCount) {
        $callCount++;

        return [0.1, 0.2];
    };

    $cache->remember('embeddings', 'input', $generate);
    $cache->remember('embeddings', 'input', $generate);

    expect($callCount)->toBe(2);
});

it('forgets cached entry', function () {
    config(['atlas.cache.ttl.embeddings' => 3600]);
    $cache = new AtlasCache(AtlasConfig::fromConfig());

    $cache->remember('embeddings', 'forget-me', fn () => [0.1, 0.2]);

    $result = $cache->forget('embeddings', 'forget-me');
    expect($result)->toBeTrue();

    // After forget, generate is called again
    $called = false;
    $cache->remember('embeddings', 'forget-me', function () use (&$called) {
        $called = true;

        return [0.3, 0.4];
    });

    expect($called)->toBeTrue();
});

it('enabled checks ttl', function () {
    config(['atlas.cache.ttl.models' => 3600]);
    $cache = new AtlasCache(AtlasConfig::fromConfig());
    expect($cache->enabled('models'))->toBeTrue();

    config(['atlas.cache.ttl.models' => 0]);
    $cache = new AtlasCache(AtlasConfig::fromConfig());
    expect($cache->enabled('models'))->toBeFalse();
});

it('key format is correct', function () {
    config(['atlas.cache.ttl.models' => 3600, 'atlas.cache.prefix' => 'atlas']);
    $cache = new AtlasCache(AtlasConfig::fromConfig());

    $callCount = 0;
    $cache->remember('models', 'my-key', function () use (&$callCount) {
        $callCount++;

        return ['model-1'];
    });

    // Verify the cache key format
    $key = 'atlas:models:my-key';
    expect(Cache::has($key))->toBeTrue();
});

it('types dont collide', function () {
    config(['atlas.cache.ttl.models' => 3600, 'atlas.cache.ttl.voices' => 3600]);
    $cache = new AtlasCache(AtlasConfig::fromConfig());

    $cache->remember('models', 'same-key', fn () => 'models-value');
    $cache->remember('voices', 'same-key', fn () => 'voices-value');

    // Forgetting one type doesn't affect the other
    $cache->forget('models', 'same-key');

    $voicesStillCached = false;
    $result = $cache->remember('voices', 'same-key', function () use (&$voicesStillCached) {
        $voicesStillCached = true;

        return 'regenerated';
    });

    expect($voicesStillCached)->toBeFalse();
    expect($result)->toBe('voices-value');
});

it('flush keys removes multiple entries', function () {
    config(['atlas.cache.ttl.models' => 3600]);
    $cache = new AtlasCache(AtlasConfig::fromConfig());

    $cache->remember('models', 'key-a', fn () => 'a');
    $cache->remember('models', 'key-b', fn () => 'b');

    $cache->flushKeys('models', ['key-a', 'key-b']);

    $calledA = false;
    $cache->remember('models', 'key-a', function () use (&$calledA) {
        $calledA = true;

        return 'regenerated';
    });

    expect($calledA)->toBeTrue();
});
