<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing;

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\Usage;
use Generator;

/**
 * Fluent builder for creating fake StreamResponse objects in tests.
 */
class StreamResponseFake
{
    protected string $text = '';

    protected int $chunkSize = 5;

    protected ?Usage $usage = null;

    protected ?FinishReason $finishReason = null;

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

    public function withUsage(Usage $usage): static
    {
        $this->usage = $usage;

        return $this;
    }

    public function withFinishReason(FinishReason $finishReason): static
    {
        $this->finishReason = $finishReason;

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

        yield new StreamChunk(
            type: ChunkType::Done,
            usage: $this->usage ?? new Usage(10, 20),
            finishReason: $this->finishReason ?? FinishReason::Stop,
        );
    }
}
