<?php

declare(strict_types=1);

use Atlasphp\Atlas\Embeddings\Chunkers\BaseTokenAwareChunker;

/**
 * Test-only subclass that exposes the protected contract of
 * BaseTokenAwareChunker so the shared packing/overlap logic can be tested
 * directly rather than only through MarkdownChunker.
 */
function makeBaseChunker(
    int $chunkSize = 50,
    int $chunkOverlap = 0,
    ?int $hardMaxTokens = null,
    ?int $maxChunksPerRecord = null,
): BaseTokenAwareChunker {
    return new class($chunkSize, $chunkOverlap, $hardMaxTokens, $maxChunksPerRecord) extends BaseTokenAwareChunker
    {
        public function __construct(
            int $chunkSize,
            int $chunkOverlap,
            private readonly ?int $hardMax,
            private readonly ?int $maxChunks,
        ) {
            parent::__construct(chunkSize: $chunkSize, chunkOverlap: $chunkOverlap);
        }

        public function chunk(string $content): array
        {
            return [];
        }

        /** @param  array<int, string>  $units */
        public function callPackUnits(array $units): array
        {
            return $this->packUnits($units);
        }

        public function callSplitOversizedUnit(string $unit): array
        {
            return $this->splitOversizedUnit($unit);
        }

        public function callCharWindow(string $text): array
        {
            return $this->charWindow($text);
        }

        public function callSplitSentences(string $text): array
        {
            return $this->splitSentences($text);
        }

        public function callTakeOverlapTail(string $content): string
        {
            return $this->takeOverlapTail($content);
        }

        /** @param  array<int, array{content: string, headingPath: ?string}>  $packed */
        public function callFinalize(array $packed): array
        {
            return $this->finalize($packed);
        }

        public function exposedHardMaxTokens(): int
        {
            return $this->hardMaxTokens();
        }

        public function exposedMaxChunksPerRecord(): int
        {
            return $this->maxChunksPerRecord();
        }

        protected function hardMaxTokens(): int
        {
            return $this->hardMax ?? parent::hardMaxTokens();
        }

        protected function maxChunksPerRecord(): int
        {
            return $this->maxChunks ?? parent::maxChunksPerRecord();
        }
    };
}

it('skips empty and whitespace-only units during packing', function () {
    $chunker = makeBaseChunker(chunkSize: 100);

    $packed = $chunker->callPackUnits(['', '   ', "\n\t  \n", 'real content here']);

    expect($packed)->toBe(['real content here']);
});

it('falls back to charWindow chunks when an oversized unit has no sentence breaks', function () {
    // One long "word" — no `. ` boundaries, so splitSentences yields a single
    // part and splitOversizedUnit hands off to charWindow.
    $chunker = makeBaseChunker(chunkSize: 10);
    $unit = str_repeat('abcdefghij', 20); // 200 chars, no whitespace, no period

    $packed = $chunker->callSplitOversizedUnit($unit);

    // chunkSize=10 → window=40 chars → 200/40 = 5 pieces.
    expect($packed)->toHaveCount(5);
    foreach ($packed as $piece) {
        expect(strlen($piece))->toBeLessThanOrEqual(40);
    }
    expect(implode('', $packed))->toBe($unit);
});

it('windows a single sentence into char slices when it exceeds hardMaxTokens', function () {
    // hardMaxTokens=5 (≈20 chars). splitSentences breaks on `[.!?]\s+[A-Z]`,
    // so the long sentence must start with a capital letter to be recognized
    // as a separate sentence from the opener.
    $chunker = makeBaseChunker(chunkSize: 100, hardMaxTokens: 5);
    $longSentence = 'X'.str_repeat('x', 199).'.';
    $unit = "Short opener. {$longSentence}";

    $packed = $chunker->callSplitOversizedUnit($unit);

    expect(count($packed))->toBeGreaterThan(1);
    expect($packed[0])->toBe('Short opener.');
    $rest = array_slice($packed, 1);
    foreach ($rest as $slice) {
        expect(strlen($slice))->toBeLessThanOrEqual(400); // chunkSize*4
    }
    expect(implode('', $rest))->toContain(str_repeat('x', 100));
});

it('returns the input as a single element when charWindow text fits in one window', function () {
    $chunker = makeBaseChunker(chunkSize: 10);

    $result = $chunker->callCharWindow('short text under window');

    expect($result)->toBe(['short text under window']);
});

it('returns empty string from takeOverlapTail when overlap is zero', function () {
    $chunker = makeBaseChunker(chunkSize: 50, chunkOverlap: 0);

    expect($chunker->callTakeOverlapTail('some prior chunk content'))->toBe('');
});

it('returns the full trimmed content from takeOverlapTail when content is shorter than the tail window', function () {
    // chunkOverlap=20 → tailChars=80. Content is 5 chars, far shorter than 80.
    $chunker = makeBaseChunker(chunkSize: 50, chunkOverlap: 20);

    expect($chunker->callTakeOverlapTail('  hi  '))->toBe('hi');
});

it('truncates finalize output at maxChunksPerRecord and triggers a warning', function () {
    $chunker = makeBaseChunker(chunkSize: 100, maxChunksPerRecord: 2);

    $packed = [
        ['content' => 'first', 'headingPath' => null],
        ['content' => 'second', 'headingPath' => null],
        ['content' => 'third', 'headingPath' => null],
        ['content' => 'fourth', 'headingPath' => null],
    ];

    $warningFired = false;
    set_error_handler(function (int $errno) use (&$warningFired): bool {
        if ($errno === E_USER_WARNING) {
            $warningFired = true;
        }

        return true;
    }, E_USER_WARNING);

    try {
        // Re-enable warning visibility because the source uses @trigger_error
        // and we want the handler to observe it.
        $previousErrorReporting = error_reporting(E_ALL);
        $out = $chunker->callFinalize($packed);
        error_reporting($previousErrorReporting);
    } finally {
        restore_error_handler();
    }

    expect($out)->toHaveCount(2);
    expect($out[0]->content)->toBe('first');
    expect($out[1]->content)->toBe('second');
    expect($warningFired)->toBeTrue();
});

it('exposes default hardMaxTokens and maxChunksPerRecord when subclasses do not override them', function () {
    $chunker = makeBaseChunker(chunkSize: 100);

    expect($chunker->exposedHardMaxTokens())->toBe(1024);
    expect($chunker->exposedMaxChunksPerRecord())->toBe(200);
});

it('returns the input as a single-element array from splitSentences when there is no sentence boundary', function () {
    $chunker = makeBaseChunker(chunkSize: 50);

    expect($chunker->callSplitSentences('one continuous fragment without terminator'))
        ->toBe(['one continuous fragment without terminator']);
});

it('returns the input as a single-element array from splitSentences when preg_split fails on invalid UTF-8', function () {
    // The /u flag makes preg_split return false on byte sequences that aren't
    // valid UTF-8 (e.g. 0xC0 0xC1, which is illegal in any UTF-8 codepoint).
    $chunker = makeBaseChunker(chunkSize: 50);
    $malformed = "\xC0\xC1 bad utf8 input";

    expect($chunker->callSplitSentences($malformed))->toBe([$malformed]);
});
