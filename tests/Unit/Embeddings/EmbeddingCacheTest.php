<?php

declare(strict_types=1);

use Atlasphp\Atlas\Embeddings\EmbeddingCache;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->cache = new EmbeddingCache;
});

it('reads enabled state from config', function () {
    config(['atlas.embeddings.cache.enabled' => true]);
    expect($this->cache->isEnabled())->toBeTrue();

    config(['atlas.embeddings.cache.enabled' => false]);
    expect($this->cache->isEnabled())->toBeFalse();
});

it('returns cached value on hit without calling generate', function () {
    config(['atlas.embeddings.cache.enabled' => true]);

    $vector = [0.1, 0.2, 0.3];
    $callCount = 0;

    $generate = function () use ($vector, &$callCount) {
        $callCount++;

        return $vector;
    };

    // First call — generates
    $result1 = $this->cache->remember('hello', $generate);
    expect($result1)->toBe($vector);
    expect($callCount)->toBe(1);

    // Second call — cached
    $result2 = $this->cache->remember('hello', $generate);
    expect($result2)->toBe($vector);
    expect($callCount)->toBe(1);
});

it('calls generate closure on cache miss', function () {
    config(['atlas.embeddings.cache.enabled' => true]);

    $vector = [0.4, 0.5, 0.6];
    $called = false;

    $result = $this->cache->remember('test input', function () use ($vector, &$called) {
        $called = true;

        return $vector;
    });

    expect($called)->toBeTrue();
    expect($result)->toBe($vector);
});

it('bypasses cache when disabled', function () {
    config(['atlas.embeddings.cache.enabled' => false]);

    $callCount = 0;
    $generate = function () use (&$callCount) {
        $callCount++;

        return [0.1, 0.2];
    };

    $this->cache->remember('input', $generate);
    $this->cache->remember('input', $generate);

    expect($callCount)->toBe(2);
});

it('forgets cached entry', function () {
    config(['atlas.embeddings.cache.enabled' => true]);

    $this->cache->remember('forget-me', fn () => [0.1, 0.2]);

    $result = $this->cache->forget('forget-me');
    expect($result)->toBeTrue();

    // After forget, generate is called again
    $called = false;
    $this->cache->remember('forget-me', function () use (&$called) {
        $called = true;

        return [0.3, 0.4];
    });

    expect($called)->toBeTrue();
});

it('includes provider model and dimensions in cache key', function () {
    config([
        'atlas.defaults.embed.provider' => 'openai',
        'atlas.defaults.embed.model' => 'text-embedding-3-small',
        'atlas.embeddings.dimensions' => 1536,
    ]);

    $key1 = $this->cache->buildKey('hello', 'openai', 'model-a');
    $key2 = $this->cache->buildKey('hello', 'openai', 'model-b');
    $key3 = $this->cache->buildKey('hello', 'cohere', 'model-a');

    expect($key1)->not->toBe($key2);
    expect($key1)->not->toBe($key3);
    expect($key1)->toStartWith('atlas:embedding:');
});

it('produces different keys for different inputs', function () {
    $key1 = $this->cache->buildKey('input one');
    $key2 = $this->cache->buildKey('input two');

    expect($key1)->not->toBe($key2);
});

it('uses configured cache store', function () {
    config([
        'atlas.embeddings.cache.enabled' => true,
        'atlas.embeddings.cache.store' => 'array',
    ]);

    // Verify the cache store is accessed and remembers correctly
    $vector = [0.1, 0.2];
    $result = $this->cache->remember('store-test', fn () => $vector);

    expect($result)->toBe($vector);

    // Verify it's actually cached in the array store
    $key = $this->cache->buildKey('store-test');
    expect(Cache::store('array')->has($key))->toBeTrue();
});
