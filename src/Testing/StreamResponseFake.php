<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing;

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\StreamResponse;
use Generator;

/**
 * Fluent builder for creating fake StreamResponse objects in tests.
 */
class StreamResponseFake
{
    protected string $text = '';

    protected int $chunkSize = 5;

    public static function make(): self
    {
        return new self;
    }

    public function withText(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function withChunkSize(int $chunkSize): static
    {
        $this->chunkSize = $chunkSize;

        return $this;
    }

    public function toResponse(): StreamResponse
    {
        return new StreamResponse($this->generateChunks());
    }

    /**
     * @return Generator<int, StreamChunk>
     */
    protected function generateChunks(): Generator
    {
        if ($this->text !== '') {
            $chunks = str_split($this->text, $this->chunkSize);

            foreach ($chunks as $chunk) {
                yield new StreamChunk(ChunkType::Text, text: $chunk);
            }
        }

        yield new StreamChunk(ChunkType::Done);
    }
}
