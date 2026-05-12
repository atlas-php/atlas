<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Embeddings\Chunkers;

/**
 * Default chunker for markdown content.
 *
 * Strategy: parse the markdown into structural blocks (headings, paragraphs,
 * code fences, GFM tables), pick the strongest heading boundary present
 * (H1/H2 > H3 > none), then greedy-pack content blocks within each section
 * up to the configured chunk_size. Paragraphs that exceed the budget on
 * their own are sentence-split via the base class.
 *
 * Code fences and GFM tables are atomic — never split mid-block. If one
 * exceeds chunk_size it emits as a single oversized chunk; broken code or
 * truncated tables are useless for retrieval.
 *
 * No external markdown library; in-house regex-based scanning is enough
 * for this use case and keeps the package dependency-free.
 */
class MarkdownChunker extends BaseTokenAwareChunker
{
    public const HARD_MAX_TOKENS = 1024;

    public const MAX_CHUNKS_PER_RECORD = 200;

    public function chunk(string $content): array
    {
        if (trim($content) === '') {
            return [];
        }

        $blocks = $this->parseBlocks($content);
        if (empty($blocks)) {
            return [];
        }

        $splitLevel = $this->strongestHeadingLevel($blocks);
        $sections = $this->groupIntoSections($blocks, $splitLevel);

        $packed = [];
        foreach ($sections as $section) {
            $units = $section['units'];
            if (empty($units)) {
                continue;
            }
            $chunkContents = $this->packUnits($units);
            foreach ($chunkContents as $chunkContent) {
                $packed[] = [
                    'content' => $chunkContent,
                    'headingPath' => $section['headingPath'],
                ];
            }
        }

        return $this->finalize($packed);
    }

    /**
     * @return array<int, array{type: string, level?: int, text?: string, content?: string}>
     */
    private function parseBlocks(string $markdown): array
    {
        $blocks = [];
        /** @var array<int, string> $lines */
        $lines = preg_split('/\r\n|\r|\n/', $markdown) ?: [];

        /** @var array<int, string> $buffer */
        $buffer = [];
        $state = 'TEXT';
        $fence = null;

        $i = 0;
        $count = count($lines);
        while ($i < $count) {
            $line = $lines[$i];

            if ($state === 'CODE') {
                $buffer[] = $line;
                if ($fence !== null && preg_match('/^\s*'.preg_quote($fence, '/').'\s*$/', $line)) {
                    $blocks[] = ['type' => 'code', 'content' => implode("\n", $buffer)];
                    $buffer = [];
                    $state = 'TEXT';
                    $fence = null;
                }
                $i++;

                continue;
            }

            if ($state === 'TABLE') {
                if (preg_match('/^\s*\|/', $line) || preg_match('/^\s*[-:|\s]+$/', $line) && trim($line) !== '') {
                    $buffer[] = $line;
                    $i++;

                    continue;
                }
                $blocks[] = ['type' => 'table', 'content' => implode("\n", $buffer)];
                $buffer = [];
                $state = 'TEXT';
                // fall through and re-process this line
            }

            if (preg_match('/^(```|~~~)/', $line, $m)) {
                $this->emitParagraph($buffer, $blocks);
                $state = 'CODE';
                $fence = $m[1];
                $buffer = [$line];
                $i++;

                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $m)) {
                $this->emitParagraph($buffer, $blocks);
                $blocks[] = [
                    'type' => 'heading',
                    'level' => strlen($m[1]),
                    'text' => trim($m[2]),
                ];
                $i++;

                continue;
            }

            if (preg_match('/^\s*\|/', $line)) {
                $this->emitParagraph($buffer, $blocks);
                $state = 'TABLE';
                $buffer = [$line];
                $i++;

                continue;
            }

            if (trim($line) === '') {
                $this->emitParagraph($buffer, $blocks);
                $i++;

                continue;
            }

            $buffer[] = $line;
            $i++;
        }

        if ($state === 'CODE' && ! empty($buffer)) {
            $blocks[] = ['type' => 'code', 'content' => implode("\n", $buffer)];
        } elseif ($state === 'TABLE' && ! empty($buffer)) {
            $blocks[] = ['type' => 'table', 'content' => implode("\n", $buffer)];
        } else {
            $this->emitParagraph($buffer, $blocks);
        }

        return $blocks;
    }

    /**
     * @param  array<int, string>  $buffer
     * @param  array<int, array{type: string, level?: int, text?: string, content?: string}>  $blocks
     */
    private function emitParagraph(array &$buffer, array &$blocks): void
    {
        if (empty($buffer)) {
            return;
        }
        $text = trim(implode("\n", $buffer));
        if ($text !== '') {
            $blocks[] = ['type' => 'paragraph', 'content' => $text];
        }
        $buffer = [];
    }

    /**
     * Pick the deepest heading level present, capped at H4.
     *
     * The chosen level acts as a cap, not a strict equality test: any heading
     * at or above the level starts a new section, so a doc with H1/H2/H3 splits
     * at all three (each becomes a section boundary) and chunks carry the
     * full heading_path from the root. Documents that only use H2 split at H2.
     * H5/H6 are rare and usually decorative; including them would over-fragment
     * short snippets, so we cap at H4.
     *
     * @param  array<int, array{type: string, level?: int}>  $blocks
     */
    private function strongestHeadingLevel(array $blocks): ?int
    {
        $found = [];
        foreach ($blocks as $b) {
            if ($b['type'] === 'heading' && isset($b['level'])) {
                $found[$b['level']] = true;
            }
        }

        for ($level = 4; $level >= 1; $level--) {
            if (isset($found[$level])) {
                return $level;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{type: string, level?: int, text?: string, content?: string}>  $blocks
     * @return array<int, array{headingPath: ?string, units: array<int, string>}>
     */
    private function groupIntoSections(array $blocks, ?int $splitLevel): array
    {
        if ($splitLevel === null) {
            $units = [];
            foreach ($blocks as $b) {
                $units[] = $this->blockToText($b);
            }

            return [['headingPath' => null, 'units' => $units]];
        }

        $sections = [];
        $current = null;
        $stack = []; // level => text

        foreach ($blocks as $b) {
            if ($b['type'] === 'heading') {
                $level = $b['level'] ?? 1;
                foreach (array_keys($stack) as $lvl) {
                    if ($lvl >= $level) {
                        unset($stack[$lvl]);
                    }
                }
                $stack[$level] = $b['text'] ?? '';

                if ($level <= $splitLevel) {
                    if ($current !== null) {
                        $sections[] = $current;
                    }
                    ksort($stack);
                    $current = [
                        'headingPath' => implode(' > ', $stack),
                        'units' => [],
                    ];

                    continue;
                }

                $headingText = str_repeat('#', $level).' '.($b['text'] ?? '');
                if ($current === null) {
                    $current = ['headingPath' => null, 'units' => [$headingText]];
                } else {
                    $current['units'][] = $headingText;
                }

                continue;
            }

            $unit = $this->blockToText($b);
            if ($current === null) {
                $current = ['headingPath' => null, 'units' => [$unit]];
            } else {
                $current['units'][] = $unit;
            }
        }

        if ($current !== null) {
            $sections[] = $current;
        }

        return $sections;
    }

    /**
     * @param  array{type: string, level?: int, text?: string, content?: string}  $block
     */
    private function blockToText(array $block): string
    {
        if ($block['type'] === 'heading') {
            return str_repeat('#', $block['level'] ?? 1).' '.($block['text'] ?? '');
        }

        return $block['content'] ?? '';
    }

    /**
     * Code fences and GFM tables are atomic — never split. If one exceeds
     * chunk_size on its own, emit as a single oversized chunk rather than
     * sentence-splitting (a broken code block is useless for retrieval).
     *
     * @return array<int, string>
     */
    protected function splitOversizedUnit(string $unit): array
    {
        $trimmed = ltrim($unit);
        if (
            str_starts_with($trimmed, '```')
            || str_starts_with($trimmed, '~~~')
            || str_starts_with($trimmed, '|')
        ) {
            @trigger_error(
                'MarkdownChunker: atomic block exceeds chunk_size; emitting as oversized chunk.',
                E_USER_WARNING
            );

            return [$unit];
        }

        return parent::splitOversizedUnit($unit);
    }

    protected function hardMaxTokens(): int
    {
        return self::HARD_MAX_TOKENS;
    }

    protected function maxChunksPerRecord(): int
    {
        return self::MAX_CHUNKS_PER_RECORD;
    }
}
