<?php

declare(strict_types=1);

use Atlasphp\Atlas\AtlasCache;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->cache = new AtlasCache;
});

it('remembers a value by type and key', function () {
    config(['atlas.cache.ttl.embeddings' => 3600]);

    $vector = [0.1, 0.2, 0.3];
    $callCount = 0;

    $generate = function () use ($vector, &$callCount) {
        $callCount++;

        return $vector;
    };

    // First call — generates
    $result1 = $this->cache->remember('embeddings', 'test-key', $generate);
    expect($result1)->toBe($vector);
    expect($callCount)->toBe(1);

    // Second call — cached
    $result2 = $this->cache->remember('embeddings', 'test-key', $generate);
    expect($result2)->toBe($vector);
    expect($callCount)->toBe(1);
});

it('bypasses cache when ttl is zero', function () {
    config(['atlas.cache.ttl.embeddings' => 0]);

    $callCount = 0;
    $generate = function () use (&$callCount) {
        $callCount++;

        return [0.1, 0.2];
    };

    $this->cache->remember('embeddings', 'input', $generate);
    $this->cache->remember('embeddings', 'input', $generate);

    expect($callCount)->toBe(2);
});

it('forgets cached entry', function () {
    config(['atlas.cache.ttl.embeddings' => 3600]);

    $this->cache->remember('embeddings', 'forget-me', fn () => [0.1, 0.2]);

    $result = $this->cache->forget('embeddings', 'forget-me');
    expect($result)->toBeTrue();

    // After forget, generate is called again
    $called = false;
    $this->cache->remember('embeddings', 'forget-me', function () use (&$called) {
        $called = true;

        return [0.3, 0.4];
    });

    expect($called)->toBeTrue();
});

it('enabled checks ttl', function () {
    config(['atlas.cache.ttl.models' => 3600]);
    expect($this->cache->enabled('models'))->toBeTrue();

    config(['atlas.cache.ttl.models' => 0]);
    expect($this->cache->enabled('models'))->toBeFalse();
});

it('key format is correct', function () {
    config(['atlas.cache.ttl.models' => 3600, 'atlas.cache.prefix' => 'atlas']);

    $callCount = 0;
    $this->cache->remember('models', 'my-key', function () use (&$callCount) {
        $callCount++;

        return ['model-1'];
    });

    // Verify the cache key format
    $key = 'atlas:models:my-key';
    expect(Cache::has($key))->toBeTrue();
});

it('types dont collide', function () {
    config(['atlas.cache.ttl.models' => 3600, 'atlas.cache.ttl.voices' => 3600]);

    $this->cache->remember('models', 'same-key', fn () => 'models-value');
    $this->cache->remember('voices', 'same-key', fn () => 'voices-value');

    // Forgetting one type doesn't affect the other
    $this->cache->forget('models', 'same-key');

    $voicesStillCached = false;
    $result = $this->cache->remember('voices', 'same-key', function () use (&$voicesStillCached) {
        $voicesStillCached = true;

        return 'regenerated';
    });

    expect($voicesStillCached)->toBeFalse();
    expect($result)->toBe('voices-value');
});

it('flush keys removes multiple entries', function () {
    config(['atlas.cache.ttl.models' => 3600]);

    $this->cache->remember('models', 'key-a', fn () => 'a');
    $this->cache->remember('models', 'key-b', fn () => 'b');

    $this->cache->flushKeys('models', ['key-a', 'key-b']);

    $calledA = false;
    $this->cache->remember('models', 'key-a', function () use (&$calledA) {
        $calledA = true;

        return 'regenerated';
    });

    expect($calledA)->toBeTrue();
});
