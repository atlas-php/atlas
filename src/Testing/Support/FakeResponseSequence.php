<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing\Support;

use Atlasphp\Atlas\Agents\Support\AgentResponse;

/**
 * Manages a sequence of fake responses for testing.
 *
 * Returns responses in order, with optional fallback when sequence
 * is exhausted.
 */
final class FakeResponseSequence
{
    /**
     * @var array<int, AgentResponse|\Throwable>
     */
    private array $responses;

    private int $index = 0;

    private AgentResponse|\Throwable|null $whenEmpty = null;

    /**
     * @param  array<int, AgentResponse|\Throwable>  $responses  The responses to return in sequence.
     */
    public function __construct(array $responses = [])
    {
        $this->responses = $responses;
    }

    /**
     * Add a response to the sequence.
     */
    public function push(AgentResponse|\Throwable $response): self
    {
        $this->responses[] = $response;

        return $this;
    }

    /**
     * Get the next response in the sequence.
     */
    public function next(): AgentResponse|\Throwable
    {
        if (! $this->hasMore()) {
            if ($this->whenEmpty === null) {
                return AgentResponse::empty();
            }

            return $this->whenEmpty;
        }

        return $this->responses[$this->index++];
    }

    /**
     * Check if there are more responses in the sequence.
     */
    public function hasMore(): bool
    {
        return $this->index < count($this->responses);
    }

    /**
     * Set the response to return when the sequence is exhausted.
     */
    public function whenEmpty(AgentResponse|\Throwable $response): self
    {
        $this->whenEmpty = $response;

        return $this;
    }

    /**
     * Reset the sequence to the beginning.
     */
    public function reset(): self
    {
        $this->index = 0;

        return $this;
    }

    /**
     * Check if the sequence is empty.
     */
    public function isEmpty(): bool
    {
        return count($this->responses) === 0;
    }

    /**
     * Get the total number of responses in the sequence.
     */
    public function count(): int
    {
        return count($this->responses);
    }
}
