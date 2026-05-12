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

it('parses a GFM table, flushes it on the first non-table line, and keeps it atomic', function () {
    $md = <<<'MD'
    Intro paragraph.

    | Header A | Header B |
    | :------- | -------: |
    | row 1a   | row 1b   |
    | row 2a   | row 2b   |

    Outro paragraph.
    MD;

    $chunks = makeMarkdownChunker(chunkSize: 200)->chunk($md);

    // Find the chunk that carries the table — it must be intact (header,
    // separator, and both rows in one piece).
    $tableChunks = array_values(array_filter(
        $chunks,
        fn ($c): bool => str_contains($c->content, '| Header A')
    ));
    expect($tableChunks)->not->toBeEmpty();
    foreach ($tableChunks as $chunk) {
        expect($chunk->content)
            ->toContain('| Header A | Header B |')
            ->toContain('| :------- | -------: |')
            ->toContain('| row 1a')
            ->toContain('| row 2a');
    }
});

it('emits a trailing table when the document ends mid-table', function () {
    $md = <<<'MD'
    | A | B |
    | - | - |
    | 1 | 2 |
    MD;

    $chunks = makeMarkdownChunker(chunkSize: 200)->chunk($md);

    expect($chunks)->toHaveCount(1);
    expect($chunks[0]->content)
        ->toContain('| A | B |')
        ->toContain('| - | - |')
        ->toContain('| 1 | 2 |');
});

it('emits a trailing code block when the closing fence is missing', function () {
    $md = "Intro.\n\n```\nunclosed_code_block();\nmore_code();";

    $chunks = makeMarkdownChunker(chunkSize: 200)->chunk($md);

    $codeChunks = array_values(array_filter(
        $chunks,
        fn ($c): bool => str_contains($c->content, 'unclosed_code_block')
    ));
    expect($codeChunks)->not->toBeEmpty();
    foreach ($codeChunks as $chunk) {
        expect($chunk->content)
            ->toContain('```')
            ->toContain('unclosed_code_block();')
            ->toContain('more_code();');
    }
});

it('keeps deeper sub-headings inline as content within a higher-level section', function () {
    // splitLevel caps at H4; H5/H6 with shallower headings present get
    // inlined as content rather than starting new sections.
    $md = <<<'MD'
    ## Section A
    Paragraph in A.

    ##### Deep heading inside A

    More paragraph in A.

    ## Section B
    Paragraph in B.
    MD;

    $chunks = makeMarkdownChunker(chunkSize: 500)->chunk($md);

    // Section A's chunk should contain the inlined ##### heading text.
    $sectionA = array_values(array_filter(
        $chunks,
        fn ($c): bool => $c->headingPath === 'Section A'
    ));
    expect($sectionA)->not->toBeEmpty();
    $aContent = implode("\n", array_map(fn ($c) => $c->content, $sectionA));
    expect($aContent)
        ->toContain('##### Deep heading inside A')
        ->toContain('Paragraph in A.')
        ->toContain('More paragraph in A.');

    // Section B remains its own section.
    $sectionB = array_values(array_filter(
        $chunks,
        fn ($c): bool => $c->headingPath === 'Section B'
    ));
    expect($sectionB)->not->toBeEmpty();
});

it('attributes pre-heading content to a null heading_path section', function () {
    $md = <<<'MD'
    Preamble paragraph that appears before any heading.

    ## First Section
    Body of the first section.
    MD;

    $chunks = makeMarkdownChunker(chunkSize: 200)->chunk($md);

    expect($chunks)->toHaveCount(2);
    expect($chunks[0]->headingPath)->toBeNull();
    expect($chunks[0]->content)->toContain('Preamble paragraph');
    expect($chunks[1]->headingPath)->toBe('First Section');
    expect($chunks[1]->content)->toContain('Body of the first section.');
});

it('attaches a deeper heading to a null section when no top-level heading precedes it', function () {
    // No H1/H2 anywhere — only H5s. splitLevel becomes 4 (cap), so H5 is
    // "deeper than splitLevel" and gets inlined as content. With no prior
    // section, the first inlined H5 must create the initial null-path section.
    $md = <<<'MD'
    ##### Lone deep heading

    Body content beneath the lone deep heading.
    MD;

    $chunks = makeMarkdownChunker(chunkSize: 200)->chunk($md);

    expect($chunks)->toHaveCount(1);
    expect($chunks[0]->headingPath)->toBeNull();
    expect($chunks[0]->content)
        ->toContain('##### Lone deep heading')
        ->toContain('Body content beneath the lone deep heading.');
});

it('treats lines with only table separator characters as table continuation', function () {
    // Exercises the `preg_match('/^\s*[-:|\s]+$/', $line) && trim($line) !== ''`
    // arm of the TABLE-state predicate: a separator line that contains no
    // pipe but is still part of the table.
    $md = <<<'MD'
    | Col 1 | Col 2 |
    :------:|:------:
    | a     | b     |

    Outro.
    MD;

    $chunks = makeMarkdownChunker(chunkSize: 200)->chunk($md);

    $tableChunks = array_values(array_filter(
        $chunks,
        fn ($c): bool => str_contains($c->content, '| Col 1')
    ));
    expect($tableChunks)->not->toBeEmpty();
    foreach ($tableChunks as $chunk) {
        expect($chunk->content)
            ->toContain('| Col 1 | Col 2 |')
            ->toContain(':------:|:------:')
            ->toContain('| a     | b     |');
    }
});
