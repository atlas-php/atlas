<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing\Support;

use Illuminate\Support\Collection;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Text\Response as PrismResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

/**
 * Manages a sequence of fake responses for testing.
 *
 * Returns responses in order, with optional fallback when sequence
 * is exhausted. Uses Prism's native Response objects.
 */
final class FakeResponseSequence
{
    /**
     * @var array<int, PrismResponse|\Throwable>
     */
    private array $responses;

    private int $index = 0;

    private PrismResponse|\Throwable|null $whenEmpty = null;

    /**
     * @param  array<int, PrismResponse|\Throwable>  $responses  The responses to return in sequence.
     */
    public function __construct(array $responses = [])
    {
        $this->responses = $responses;
    }

    /**
     * Add a response to the sequence.
     */
    public function push(PrismResponse|\Throwable $response): self
    {
        $this->responses[] = $response;

        return $this;
    }

    /**
     * Get the next response in the sequence.
     */
    public function next(): PrismResponse|\Throwable
    {
        if (! $this->hasMore()) {
            if ($this->whenEmpty === null) {
                return self::emptyResponse();
            }

            return $this->whenEmpty;
        }

        return $this->responses[$this->index++];
    }

    /**
     * Create an empty Prism response.
     */
    public static function emptyResponse(): PrismResponse
    {
        return new PrismResponse(
            steps: new Collection([]),
            text: '',
            finishReason: FinishReason::Stop,
            toolCalls: [],
            toolResults: [],
            usage: new Usage(promptTokens: 0, completionTokens: 0),
            meta: new Meta(id: 'fake-id', model: 'fake-model'),
            messages: new Collection([]),
        );
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
    public function whenEmpty(PrismResponse|\Throwable $response): self
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
