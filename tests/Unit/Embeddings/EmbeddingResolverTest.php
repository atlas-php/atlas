<?php

declare(strict_types=1);

use Atlasphp\Atlas\Embeddings\EmbeddingCache;
use Atlasphp\Atlas\Embeddings\EmbeddingResolver;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Testing\EmbeddingsResponseFake;

beforeEach(function () {
    config([
        'atlas.defaults.embed.provider' => 'openai',
        'atlas.defaults.embed.model' => 'text-embedding-3-small',
    ]);

    $this->cache = new EmbeddingCache;
    $this->resolver = new EmbeddingResolver($this->cache);
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
    config(['atlas.embeddings.cache.enabled' => true]);

    $vector = [0.4, 0.5, 0.6];

    Atlas::fake([
        EmbeddingsResponseFake::make()->withEmbeddings([$vector]),
    ]);

    $result1 = $this->resolver->resolve('cached input');
    $result2 = $this->resolver->resolve('cached input');

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
})->throws(RuntimeException::class, 'Provider returned no embeddings for the given input.');

it('throws RuntimeException when resolveUsing returns empty embeddings', function () {
    Atlas::fake([
        EmbeddingsResponseFake::make()->withEmbeddings([]),
    ]);

    $this->resolver->resolveUsing('test input', 'openai', 'text-embedding-3-small');
})->throws(RuntimeException::class, 'Provider returned no embeddings for the given input.');
