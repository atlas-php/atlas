<?php

declare(strict_types=1);

use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\AtlasCache;
use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Embeddings\EmbeddingResolver;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Testing\EmbeddingsResponseFake;

beforeEach(function () {
    config([
        'atlas.defaults.embed.provider' => 'openai',
        'atlas.defaults.embed.model' => 'text-embedding-3-small',
        'atlas.cache.ttl.embeddings' => 0,
    ]);
    AtlasConfig::refresh();

    $atlasConfig = app(AtlasConfig::class);
    $this->cache = new AtlasCache($atlasConfig);
    $this->resolver = new EmbeddingResolver($this->cache, $atlasConfig);
});

it('resolves a string into an embedding vector', function () {
    $vector = [0.1, 0.2, 0.3];

    Atlas::fake([
        EmbeddingsResponseFake::make()->withEmbeddings([$vector]),
    ]);

    $result = $this->resolver->resolve('test input');

    expect($result)->toBe($vector);
});

it('returns cached value when cache is enabled', function () {
    config(['atlas.cache.ttl.embeddings' => 3600]);
    $atlasConfig = AtlasConfig::fromConfig();
    $cache = new AtlasCache($atlasConfig);
    $resolver = new EmbeddingResolver($cache, $atlasConfig);

    $vector = [0.4, 0.5, 0.6];

    Atlas::fake([
        EmbeddingsResponseFake::make()->withEmbeddings([$vector]),
    ]);

    $result1 = $resolver->resolve('cached input');
    $result2 = $resolver->resolve('cached input');

    expect($result1)->toBe($vector);
    expect($result2)->toBe($vector);

    // Atlas::fake only has one response — second call would fail if not cached
});

it('passes explicit provider and model via resolveUsing', function () {
    $vector = [0.7, 0.8, 0.9];

    Atlas::fake([
        EmbeddingsResponseFake::make()->withEmbeddings([$vector]),
    ]);

    $result = $this->resolver->resolveUsing('test', 'openai', 'text-embedding-3-small');

    expect($result)->toBe($vector);
});

it('throws RuntimeException when provider returns empty embeddings', function () {
    Atlas::fake([
        EmbeddingsResponseFake::make()->withEmbeddings([]),
    ]);

    $this->resolver->resolve('test input');
})->throws(AtlasException::class, 'Provider returned no embeddings for the given input.');

it('throws RuntimeException when resolveUsing returns empty embeddings', function () {
    Atlas::fake([
        EmbeddingsResponseFake::make()->withEmbeddings([]),
    ]);

    $this->resolver->resolveUsing('test input', 'openai', 'text-embedding-3-small');
})->throws(AtlasException::class, 'Provider returned no embeddings for the given input.');

it('forget removes cached embedding', function () {
    config(['atlas.cache.ttl.embeddings' => 3600]);
    $atlasConfig = AtlasConfig::fromConfig();
    $cache = new AtlasCache($atlasConfig);
    $resolver = new EmbeddingResolver($cache, $atlasConfig);

    $vector = [0.1, 0.2, 0.3];

    Atlas::fake([
        EmbeddingsResponseFake::make()->withEmbeddings([$vector]),
        EmbeddingsResponseFake::make()->withEmbeddings([[0.9, 0.8, 0.7]]),
    ]);

    // Cache the first value
    $result1 = $resolver->resolve('forget test');
    expect($result1)->toBe($vector);

    // Forget it
    $resolver->forget('forget test');

    // Next call should get the second response (cache miss)
    $result2 = $resolver->resolve('forget test');
    expect($result2)->toBe([0.9, 0.8, 0.7]);
});

it('generates unique cache keys for different providers', function () {
    config(['atlas.cache.ttl.embeddings' => 3600]);
    $atlasConfig = AtlasConfig::fromConfig();
    $cache = new AtlasCache($atlasConfig);
    $resolver = new EmbeddingResolver($cache, $atlasConfig);

    $vector1 = [0.1, 0.2];
    $vector2 = [0.3, 0.4];

    Atlas::fake([
        EmbeddingsResponseFake::make()->withEmbeddings([$vector1]),
        EmbeddingsResponseFake::make()->withEmbeddings([$vector2]),
    ]);

    $result1 = $resolver->resolveUsing('same text', 'openai', 'text-embedding-3-small');
    $result2 = $resolver->resolveUsing('same text', 'openai', 'text-embedding-3-large');

    expect($result1)->toBe($vector1)
        ->and($result2)->toBe($vector2);
});

it('generates unique cache keys for different dimensions', function () {
    config(['atlas.cache.ttl.embeddings' => 3600, 'atlas.embeddings.dimensions' => 1536]);
    $atlasConfig = AtlasConfig::fromConfig();
    $cache = new AtlasCache($atlasConfig);
    $resolver = new EmbeddingResolver($cache, $atlasConfig);

    $vector1 = [0.1, 0.2];

    Atlas::fake([
        EmbeddingsResponseFake::make()->withEmbeddings([$vector1]),
        EmbeddingsResponseFake::make()->withEmbeddings([[0.9, 0.8]]),
    ]);

    $result1 = $resolver->resolve('dimension test');

    config(['atlas.embeddings.dimensions' => 768]);
    $atlasConfig2 = AtlasConfig::fromConfig();
    $cache2 = new AtlasCache($atlasConfig2);
    $resolver2 = new EmbeddingResolver($cache2, $atlasConfig2);
    $result2 = $resolver2->resolve('dimension test');

    // Different dimensions should produce different cache keys
    expect($result1)->toBe($vector1)
        ->and($result2)->toBe([0.9, 0.8]);
});
