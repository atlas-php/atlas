<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Embeddings\Chunkers;

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Embeddings\ChunkData;
use Atlasphp\Atlas\Support\TokenCounter;

/**
 * Abstract base for token-aware chunkers.
 *
 * Concrete chunkers split content into "units" using format-specific boundaries
 * (headings, paragraphs, sentences, function declarations, etc.) and feed
 * those units into the shared pack/overlap logic on this class. The split
 * strategy is what changes between chunkers; the packing math does not.
 *
 * Hard limits — HARD_MAX_TOKENS, MAX_CHUNKS_PER_RECORD — are class constants
 * on concrete chunkers, not config. Consumers who need different values swap
 * the chunker out rather than tweak global config.
 */
abstract class BaseTokenAwareChunker implements Chunker
{
    protected int $chunkSize;

    protected int $chunkOverlap;

    public function __construct(
        ?int $chunkSize = null,
        ?int $chunkOverlap = null,
        ?AtlasConfig $config = null,
    ) {
        // Inject AtlasConfig when caller has it; fall back to the container
        // only when neither config nor explicit sizes are provided. This
        // lets chunkers be unit-tested without bootstrapping the container.
        if ($chunkSize === null || $chunkOverlap === null) {
            $config ??= app(AtlasConfig::class);
        }
        $this->chunkSize = $chunkSize ?? $config->chunkSize;
        $this->chunkOverlap = $chunkOverlap ?? $config->chunkOverlap;
    }

    /**
     * Pack already-split string units into chunks under the size budget.
     *
     * Greedy: accumulate units until adding the next would exceed chunkSize.
     * Any unit larger than the budget by itself gets recursed via
     * splitOversizedUnit() — typically into sentences.
     *
     * @param  array<int, string>  $units
     * @return array<int, string> Packed chunk contents (without overlap applied)
     */
    protected function packUnits(array $units): array
    {
        $packed = [];
        $buffer = [];
        $bufferTokens = 0;

        foreach ($units as $unit) {
            $unit = trim($unit);
            if ($unit === '') {
                continue;
            }

            $unitTokens = TokenCounter::count($unit);

            if ($unitTokens > $this->chunkSize) {
                if (! empty($buffer)) {
                    $packed[] = implode("\n\n", $buffer);
                    $buffer = [];
                    $bufferTokens = 0;
                }
                foreach ($this->splitOversizedUnit($unit) as $sub) {
                    $packed[] = $sub;
                }

                continue;
            }

            if ($bufferTokens + $unitTokens > $this->chunkSize && ! empty($buffer)) {
                $packed[] = implode("\n\n", $buffer);
                $buffer = [];
                $bufferTokens = 0;
            }

            $buffer[] = $unit;
            $bufferTokens += $unitTokens;
        }

        if (! empty($buffer)) {
            $packed[] = implode("\n\n", $buffer);
        }

        return $packed;
    }

    /**
     * Handle a single unit that exceeds chunkSize on its own.
     *
     * Default: split on sentences and re-pack. Subclasses override for
     * content types where sentence splitting doesn't make sense (e.g.
     * source code — pack by lines instead).
     *
     * @return array<int, string>
     */
    protected function splitOversizedUnit(string $unit): array
    {
        $sentences = $this->splitSentences($unit);

        if (count($sentences) <= 1) {
            return $this->charWindow($unit);
        }

        $packed = [];
        $buffer = [];
        $bufferTokens = 0;

        foreach ($sentences as $sentence) {
            $sentenceTokens = TokenCounter::count($sentence);

            if ($sentenceTokens > $this->hardMaxTokens()) {
                if (! empty($buffer)) {
                    $packed[] = implode(' ', $buffer);
                    $buffer = [];
                    $bufferTokens = 0;
                }
                foreach ($this->charWindow($sentence) as $sub) {
                    $packed[] = $sub;
                }

                continue;
            }

            if ($bufferTokens + $sentenceTokens > $this->chunkSize && ! empty($buffer)) {
                $packed[] = implode(' ', $buffer);
                $buffer = [];
                $bufferTokens = 0;
            }

            $buffer[] = $sentence;
            $bufferTokens += $sentenceTokens;
        }

        if (! empty($buffer)) {
            $packed[] = implode(' ', $buffer);
        }

        return $packed;
    }

    /**
     * Last-resort char window for sentences that exceed hardMaxTokens.
     *
     * @return array<int, string>
     */
    protected function charWindow(string $text): array
    {
        $window = $this->chunkSize * 4;
        $length = mb_strlen($text);

        if ($length <= $window) {
            return [$text];
        }

        $out = [];
        for ($i = 0; $i < $length; $i += $window) {
            $out[] = mb_substr($text, $i, $window);
        }

        return $out;
    }

    /**
     * Naive sentence split. Good enough for English prose.
     *
     * @return array<int, string>
     */
    protected function splitSentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+(?=[A-Z])/u', $text);
        if ($parts === false) {
            return [$text];
        }

        return array_values(array_filter($parts, fn (string $s): bool => trim($s) !== ''));
    }

    /**
     * Take the last chunkOverlap "tokens" worth of text as a prefix for the next chunk.
     *
     * Aligns to the nearest whitespace inside the tail so overlap doesn't
     * start mid-word — embeddings of partial words don't help retrieval.
     */
    protected function takeOverlapTail(string $content): string
    {
        if ($this->chunkOverlap <= 0) {
            return '';
        }

        $tailChars = $this->chunkOverlap * 4;
        if (mb_strlen($content) <= $tailChars) {
            return trim($content);
        }

        $tail = mb_substr($content, -$tailChars);
        $space = mb_strpos($tail, ' ');
        if ($space !== false && $space < $tailChars / 2) {
            $tail = mb_substr($tail, $space + 1);
        }

        return trim($tail);
    }

    /**
     * Apply between-chunk overlap and finalize as ChunkData with stable ord.
     *
     * Overlap is applied ONLY within the same section (same heading_path).
     * Crossing a heading boundary means topic context shifts, so prepending
     * the previous section's tail would smear the heading attribution and
     * make retrieval scores noisier.
     *
     * @param  array<int, array{content: string, headingPath: ?string}>  $packed
     * @return array<int, ChunkData>
     */
    protected function finalize(array $packed): array
    {
        $out = [];
        $previousContent = null;
        $previousHeading = null;
        $cap = $this->maxChunksPerRecord();

        foreach ($packed as $i => $piece) {
            if ($i >= $cap) {
                @trigger_error(
                    'Chunker exceeded max_chunks_per_record cap of '.$cap.'; remaining content truncated.',
                    E_USER_WARNING
                );
                break;
            }

            $content = $piece['content'];
            $sameSection = $previousContent !== null
                && $previousHeading === $piece['headingPath'];

            if ($sameSection && $this->chunkOverlap > 0) {
                $overlap = $this->takeOverlapTail($previousContent);
                if ($overlap !== '') {
                    $content = $overlap."\n\n".$content;
                }
            }

            $out[] = new ChunkData(
                ord: $i,
                headingPath: $piece['headingPath'],
                content: $content,
                tokenCount: TokenCounter::count($content),
            );

            $previousContent = $piece['content'];
            $previousHeading = $piece['headingPath'];
        }

        return $out;
    }

    /**
     * Absolute ceiling for a single chunk. A chunk exceeding this logs a warning
     * but is still emitted — splitting it further would be useless for retrieval.
     */
    protected function hardMaxTokens(): int
    {
        return 1024;
    }

    /**
     * Cap on chunks per record. Hit on absurdly long inputs; logs a warning.
     */
    protected function maxChunksPerRecord(): int
    {
        return 200;
    }
}
