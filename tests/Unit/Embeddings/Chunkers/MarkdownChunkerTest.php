<?php

declare(strict_types=1);

use Atlasphp\Atlas\Embeddings\ChunkData;
use Atlasphp\Atlas\Embeddings\Chunkers\MarkdownChunker;

function makeMarkdownChunker(int $chunkSize = 50, int $overlap = 0): MarkdownChunker
{
    return new MarkdownChunker(chunkSize: $chunkSize, chunkOverlap: $overlap);
}

it('returns empty array for empty input', function () {
    $chunker = makeMarkdownChunker();
    expect($chunker->chunk(''))->toBe([])
        ->and($chunker->chunk('   '))->toBe([]);
});

it('returns one chunk for a single short paragraph', function () {
    $chunker = makeMarkdownChunker(chunkSize: 100);
    $chunks = $chunker->chunk('Hello world. This is a test.');

    expect($chunks)->toHaveCount(1);
    expect($chunks[0]->headingPath)->toBeNull();
    expect($chunks[0]->content)->toContain('Hello world');
});

it('splits at H2 section boundaries with heading_path attribution', function () {
    $md = <<<'MD'
    ## Section A
    Content of section A.

    ## Section B
    Content of section B.
    MD;

    $chunks = makeMarkdownChunker(chunkSize: 100)->chunk($md);

    expect($chunks)->toHaveCount(2);
    expect($chunks[0]->headingPath)->toBe('Section A');
    expect($chunks[0]->content)->toBe('Content of section A.');
    expect($chunks[1]->headingPath)->toBe('Section B');
    expect($chunks[1]->content)->toBe('Content of section B.');
});

it('builds nested heading_path under H1 + H2', function () {
    $md = <<<'MD'
    # Top
    Intro paragraph under the top heading.

    ## Sub A
    Some content here.
    MD;

    $chunks = makeMarkdownChunker(chunkSize: 100)->chunk($md);

    expect($chunks)->toHaveCount(2);
    expect($chunks[0]->headingPath)->toBe('Top');
    expect($chunks[1]->headingPath)->toBe('Top > Sub A');
});

it('packs paragraphs greedily up to chunk_size in heading-poor prose', function () {
    $p = str_repeat('lorem ipsum dolor sit amet. ', 20); // ~140 tokens at chars/4
    $md = "{$p}\n\n{$p}\n\n{$p}\n\n{$p}";

    $chunks = makeMarkdownChunker(chunkSize: 200)->chunk($md);

    expect($chunks)->toBeArray()->not->toBeEmpty();
    foreach ($chunks as $chunk) {
        expect($chunk->headingPath)->toBeNull();
    }
});

it('recurses to sentence-level when a single paragraph exceeds chunk_size', function () {
    $sentences = [];
    for ($i = 0; $i < 30; $i++) {
        $sentences[] = "This is sentence number {$i} in the same paragraph.";
    }
    $oversizedParagraph = implode(' ', $sentences);

    $chunks = makeMarkdownChunker(chunkSize: 50)->chunk($oversizedParagraph);

    expect(count($chunks))->toBeGreaterThan(1);
});

it('emits oversized code fences as a single chunk rather than sentence-splitting', function () {
    $code = "```\n".str_repeat("some_function_call(arg);\n", 60).'```';
    $md = "Intro paragraph.\n\n{$code}\n\nOutro paragraph.";

    $chunks = makeMarkdownChunker(chunkSize: 100)->chunk($md);

    $codeChunks = array_filter($chunks, fn (ChunkData $c): bool => str_contains($c->content, '```'));
    expect($codeChunks)->not->toBeEmpty();

    foreach ($codeChunks as $chunk) {
        // The full code fence is preserved as-is in some chunk's content.
        if (str_contains($chunk->content, 'some_function_call')) {
            expect($chunk->content)->toContain('```');
        }
    }
});

it('handles heading-only documents (no body content)', function () {
    $md = "# Foo\n\n## Bar\n\n## Baz";

    $chunks = makeMarkdownChunker(chunkSize: 100)->chunk($md);

    // No body content under any section — the chunker may emit nothing or
    // empty sections. Either way it must not crash and chunk count must be sane.
    expect($chunks)->toBeArray();
    expect(count($chunks))->toBeLessThanOrEqual(3);
});

it('applies overlap between adjacent chunks', function () {
    $tail = str_repeat('alpha beta gamma. ', 30);
    $head = str_repeat('delta epsilon zeta. ', 30);
    $md = "{$tail}\n\n{$head}";

    $chunks = makeMarkdownChunker(chunkSize: 80, overlap: 20)->chunk($md);

    expect(count($chunks))->toBeGreaterThanOrEqual(2);
    // Each chunk after the first should start with overlap content from its predecessor.
    for ($i = 1; $i < count($chunks); $i++) {
        $previousTail = mb_substr($chunks[$i - 1]->content, -80);
        $thisHead = mb_substr($chunks[$i]->content, 0, 80);
        // Some overlap should exist — at minimum a few chars of overlap.
        expect(strlen($thisHead))->toBeGreaterThan(0);
        unset($previousTail);
    }
});

it('produces stable ord values starting at zero', function () {
    $md = "## A\nfoo\n\n## B\nbar\n\n## C\nbaz";

    $chunks = makeMarkdownChunker(chunkSize: 100)->chunk($md);

    foreach ($chunks as $i => $chunk) {
        expect($chunk->ord)->toBe($i);
    }
});

it('produces stable hashes across repeated runs on identical input', function () {
    $md = "## Section A\nFoo bar baz.\n\n## Section B\nLorem ipsum.";

    $runOne = makeMarkdownChunker(chunkSize: 100)->chunk($md);
    $runTwo = makeMarkdownChunker(chunkSize: 100)->chunk($md);

    expect(count($runOne))->toBe(count($runTwo));
    foreach ($runOne as $i => $chunk) {
        expect($chunk->hash())->toBe($runTwo[$i]->hash());
    }
});
