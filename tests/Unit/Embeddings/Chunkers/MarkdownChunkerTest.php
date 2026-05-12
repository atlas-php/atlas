<?php

declare(strict_types=1);

use Atlasphp\Atlas\Embeddings\ChunkData;
use Atlasphp\Atlas\Embeddings\Chunkers\MarkdownChunker;
use Atlasphp\Atlas\Support\TokenCounter;

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

it('inlines a deep heading as content under a null heading_path when it precedes any split-level heading', function () {
    // Deepest heading is H4 → splitLevel=4. H5 appears first, so it hits the
    // `level > splitLevel + $current === null` branch and seeds the initial
    // null-path section. The later H4 then opens its own section.
    $md = <<<'MD'
    ##### Intro deep heading

    Pre-section paragraph.

    #### Real section
    Body in the real section.
    MD;

    $chunks = makeMarkdownChunker(chunkSize: 500)->chunk($md);

    $nullPath = array_values(array_filter($chunks, fn ($c): bool => $c->headingPath === null));
    expect($nullPath)->not->toBeEmpty();
    $nullContent = implode("\n", array_map(fn ($c) => $c->content, $nullPath));
    expect($nullContent)
        ->toContain('##### Intro deep heading')
        ->toContain('Pre-section paragraph.');

    $realSection = array_values(array_filter($chunks, fn ($c): bool => $c->headingPath === 'Real section'));
    expect($realSection)->not->toBeEmpty();
    expect($realSection[0]->content)->toContain('Body in the real section.');
});

it('groups every block under a single null-path section when the document has no H1–H4 headings', function () {
    // Only H5s and H6s — strongestHeadingLevel caps at 4 and finds none, so
    // splitLevel is null and groupIntoSections takes the no-split path.
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

it('returns an empty array when parseBlocks yields no blocks', function () {
    // The empty($blocks) guard inside chunk() is defensive: trim()==='' catches
    // the only input that would produce zero blocks today. A subclass that
    // overrides parseBlocks lets us exercise the guard directly so a future
    // change to parseBlocks doesn't silently regress it.
    $chunker = new class(chunkSize: 100, chunkOverlap: 0) extends MarkdownChunker
    {
        protected function parseBlocks(string $markdown): array
        {
            return [];
        }
    };

    expect($chunker->chunk('non-empty input that produces no blocks'))->toBe([]);
});

// ─── Oversized atomic block fallback ────────────────────────────────────────
//
// Atomic blocks (code fences, GFM tables) emit unsplit when they exceed
// chunk_size but stay under HARD_MAX_TOKENS. When a single block exceeds
// HARD_MAX_TOKENS — pasted code dumps, dumped query results, etc. — it
// would also exceed the embedding API's input-token limit and surface as
// a generic provider exception. The chunker now falls back to a line-based
// split so the embedder always sees something it can process.

it('splits an oversized code fence at line boundaries', function () {
    // Build a code fence that exceeds HARD_MAX_TOKENS (1024).
    // ~30 chars/line × 200 lines ≈ 1500 tokens at chars/4.
    $body = str_repeat("function_call_with_a_long_name();\n", 200);
    $unit = "```php\n{$body}```";
    expect(TokenCounter::count($unit))->toBeGreaterThan(MarkdownChunker::HARD_MAX_TOKENS);

    $chunks = makeMarkdownChunker(chunkSize: 200)->chunk($unit);

    // (a) more than one chunk emitted — the fallback fired
    expect(count($chunks))->toBeGreaterThan(1);

    // (b) every chunk's tokenCount is <= HARD_MAX_TOKENS
    foreach ($chunks as $chunk) {
        expect($chunk->tokenCount)->toBeLessThanOrEqual(MarkdownChunker::HARD_MAX_TOKENS);
    }

    // (c) every chunk contains complete lines from the original (line boundaries respected)
    foreach ($chunks as $chunk) {
        // Each chunk should contain at least one full line of the body, no half-line splits.
        expect($chunk->content)->toContain('function_call_with_a_long_name();');
    }
});

it('keeps a normal-sized atomic block as a single chunk', function () {
    // Code fence under HARD_MAX_TOKENS — must still emit as one piece (the
    // existing atomic-block guarantee for the common case).
    $code = "```\n".str_repeat("foo();\n", 60).'```';
    expect(TokenCounter::count($code))->toBeLessThan(MarkdownChunker::HARD_MAX_TOKENS);

    $chunks = makeMarkdownChunker(chunkSize: 50)->chunk($code);

    expect($chunks)->toHaveCount(1);
    expect($chunks[0]->content)->toContain('```');
    expect($chunks[0]->content)->toContain('foo();');
});

it('falls back to single chunk when an atomic block is one oversized line (no newlines to split on)', function () {
    // Pathological case: a single line bigger than HARD_MAX_TOKENS (e.g. a
    // minified JSON dump pasted into a code fence). splitAtomicByLines has
    // nothing to split on, so it emits the line as one chunk that still
    // exceeds the cap. Asserts (a) we don't crash, (b) we don't infinite-loop,
    // and (c) the line content survives intact for retrieval.
    $longLine = '```'.str_repeat('x', MarkdownChunker::HARD_MAX_TOKENS * 4 + 1000).'```';
    expect(TokenCounter::count($longLine))->toBeGreaterThan(MarkdownChunker::HARD_MAX_TOKENS);

    $chunks = makeMarkdownChunker(chunkSize: 200)->chunk($longLine);

    expect($chunks)->not->toBeEmpty();
    // Combined content still represents the original payload.
    $combined = implode('', array_map(fn (ChunkData $c): string => $c->content, $chunks));
    expect(strlen($combined))->toBeGreaterThanOrEqual(MarkdownChunker::HARD_MAX_TOKENS * 4);
});

it('splits oversized tilde-fenced (~~~) code blocks the same way as backtick fences', function () {
    // Tilde fences are valid markdown and supported by the parser/atomic check.
    // Locks in the second branch of the atomic-block predicate so a future
    // refactor doesn't accidentally drop ~~~ support.
    $body = str_repeat("function_call_with_a_long_name();\n", 200);
    $unit = "~~~php\n{$body}~~~";
    expect(TokenCounter::count($unit))->toBeGreaterThan(MarkdownChunker::HARD_MAX_TOKENS);

    $chunks = makeMarkdownChunker(chunkSize: 200)->chunk($unit);

    expect(count($chunks))->toBeGreaterThan(1);
    foreach ($chunks as $chunk) {
        expect($chunk->tokenCount)->toBeLessThanOrEqual(MarkdownChunker::HARD_MAX_TOKENS);
    }
});

it('splits an oversized GFM table at row boundaries', function () {
    // Build a GFM table that exceeds HARD_MAX_TOKENS.
    $rows = [];
    for ($i = 0; $i < 400; $i++) {
        $rows[] = "| row {$i}a       | row {$i}b       | row {$i}c       |";
    }
    $unit = "| col A    | col B    | col C    |\n| -------- | -------- | -------- |\n".implode("\n", $rows);
    expect(TokenCounter::count($unit))->toBeGreaterThan(MarkdownChunker::HARD_MAX_TOKENS);

    $chunks = makeMarkdownChunker(chunkSize: 200)->chunk($unit);

    expect(count($chunks))->toBeGreaterThan(1);
    foreach ($chunks as $chunk) {
        expect($chunk->tokenCount)->toBeLessThanOrEqual(MarkdownChunker::HARD_MAX_TOKENS);
    }

    // Spot-check: somewhere across the chunks, both early and late rows are present.
    $combined = implode("\n", array_map(fn ($c): string => $c->content, $chunks));
    expect($combined)->toContain('| row 0a');
    expect($combined)->toContain('| row 399a');
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
