<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Embeddings;

/**
 * Immutable value object describing one chunk produced by a Chunker.
 *
 * Chunkers return arrays of these to the reconciler. The hash is computed
 * over heading_path + "\n\n" + content so that re-running a chunker against
 * unchanged input produces stable hashes (the diff algorithm relies on this).
 */
final readonly class ChunkData
{
    public int $ord;

    public ?string $headingPath;

    public string $content;

    public int $tokenCount;

    public function __construct(
        int $ord,
        ?string $headingPath,
        string $content,
        int $tokenCount,
    ) {
        $this->ord = $ord;
        // Normalize empty string to null so hash() and embedText() never
        // disagree on whether a heading path is present. Without this, a
        // chunker that produces "" would produce a hash including "\n\n"
        // while embedText() returns bare content — and the next reconciler
        // run would see a hash mismatch and re-embed for no reason.
        $this->headingPath = ($headingPath === null || $headingPath === '') ? null : $headingPath;
        $this->content = $content;
        $this->tokenCount = $tokenCount;
    }

    /**
     * Stable content hash used to dedup chunks against existing rows.
     *
     * Matches atlas convention (xxh128) — non-cryptographic, fast,
     * 128 bits of entropy is more than sufficient for content dedup.
     * Computed over the exact text that embedText() returns.
     */
    public function hash(): string
    {
        return hash('xxh128', $this->embedText());
    }

    /**
     * The exact text sent to the embedding provider.
     *
     * Section context measurably improves retrieval quality; the hash is
     * computed over this same composite so identical inputs produce
     * identical hashes.
     */
    public function embedText(): string
    {
        if ($this->headingPath === null) {
            return $this->content;
        }

        return $this->headingPath."\n\n".$this->content;
    }
}
