<?php

declare(strict_types=1);

use Atlasphp\Atlas\Embeddings\ChunkData;

it('produces a deterministic xxh128 hash', function () {
    $a = new ChunkData(ord: 0, headingPath: 'Section', content: 'Hello world', tokenCount: 3);
    $b = new ChunkData(ord: 1, headingPath: 'Section', content: 'Hello world', tokenCount: 3);

    expect($a->hash())->toBe($b->hash())
        ->and(strlen($a->hash()))->toBe(32);
});

it('returns different hashes when content or heading_path differ', function () {
    $a = new ChunkData(ord: 0, headingPath: 'A', content: 'hello', tokenCount: 2);
    $b = new ChunkData(ord: 0, headingPath: 'B', content: 'hello', tokenCount: 2);
    $c = new ChunkData(ord: 0, headingPath: 'A', content: 'world', tokenCount: 2);

    expect($a->hash())->not->toBe($b->hash())
        ->and($a->hash())->not->toBe($c->hash());
});

it('embedText prepends heading path when present', function () {
    $chunk = new ChunkData(ord: 0, headingPath: 'Foo > Bar', content: 'body', tokenCount: 1);

    expect($chunk->embedText())->toBe("Foo > Bar\n\nbody");
});

it('embedText omits heading path when null or empty', function () {
    $a = new ChunkData(ord: 0, headingPath: null, content: 'body', tokenCount: 1);
    $b = new ChunkData(ord: 0, headingPath: '', content: 'body', tokenCount: 1);

    expect($a->embedText())->toBe('body')
        ->and($b->embedText())->toBe('body');
});
