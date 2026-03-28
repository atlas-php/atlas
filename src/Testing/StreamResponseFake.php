<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing;

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Responses\StreamChunk;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\Usage;
use Generator;

/**
 * Fluent builder for creating fake StreamResponse objects in tests.
 *
 * Chunk emission order: thinking → tool calls → text → done.
 */
class StreamResponseFake
{
    protected string $text = '';

    protected int $chunkSize = 5;

    protected ?Usage $usage = null;

    protected ?FinishReason $finishReason = null;

    protected ?string $thinking = null;

    /** @var array<int, ToolCall> */
    protected array $toolCalls = [];

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

    /**
     * Add thinking/reasoning content to the fake stream.
     */
    public function withThinking(string $thinking): static
    {
        $this->thinking = $thinking;

        return $this;
    }

    /**
     * Add tool calls to the fake stream.
     *
     * @param  array<int, ToolCall>  $toolCalls
     */
    public function withToolCalls(array $toolCalls): static
    {
        $this->toolCalls = $toolCalls;

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
        if ($this->thinking !== null) {
            yield new StreamChunk(ChunkType::Thinking, reasoning: $this->thinking);
        }

        if ($this->toolCalls !== []) {
            yield new StreamChunk(ChunkType::ToolCall, toolCalls: $this->toolCalls);
        }

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
