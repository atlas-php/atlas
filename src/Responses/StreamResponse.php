<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses;

use Atlasphp\Atlas\Enums\FinishReason;
use Generator;
use IteratorAggregate;

/**
 * A streaming response that yields chunks and accumulates text.
 *
 * @implements IteratorAggregate<int, StreamChunk>
 */
class StreamResponse implements IteratorAggregate
{
    protected string $accumulatedText = '';

    protected ?Usage $usage = null;

    protected ?FinishReason $finishReason = null;

    /**
     * @param  iterable<StreamChunk>  $source
     */
    public function __construct(
        protected readonly iterable $source,
    ) {}

    /**
     * @return Generator<int, StreamChunk>
     */
    public function getIterator(): Generator
    {
        foreach ($this->source as $chunk) {
            if ($chunk->text !== null) {
                $this->accumulatedText .= $chunk->text;
            }

            yield $chunk;
        }
    }

    /**
     * Get the accumulated text after iteration.
     */
    public function getText(): string
    {
        return $this->accumulatedText;
    }

    /**
     * Get the usage data, if available after iteration.
     */
    public function getUsage(): ?Usage
    {
        return $this->usage;
    }

    /**
     * Get the finish reason, if available after iteration.
     */
    public function getFinishReason(): ?FinishReason
    {
        return $this->finishReason;
    }
}
