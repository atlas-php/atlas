<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Streaming;

use Atlasphp\Atlas\Streaming\Events\ErrorEvent;
use Atlasphp\Atlas\Streaming\Events\StreamEndEvent;
use Atlasphp\Atlas\Streaming\Events\TextDeltaEvent;
use Atlasphp\Atlas\Streaming\Events\ToolCallEndEvent;
use Atlasphp\Atlas\Streaming\Events\ToolCallStartEvent;
use Closure;
use Generator;
use IteratorAggregate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Traversable;

/**
 * Wrapper for streaming responses with helper methods.
 *
 * Provides an iterable interface for stream events with convenience
 * methods for text accumulation, SSE responses, and event filtering.
 *
 * @implements IteratorAggregate<int, StreamEvent>
 */
class StreamResponse implements IteratorAggregate
{
    /**
     * Collected events after iteration.
     *
     * @var array<int, StreamEvent>
     */
    private array $events = [];

    /**
     * Accumulated text from TextDeltaEvents.
     */
    private string $accumulatedText = '';

    /**
     * The final StreamEndEvent if stream completed.
     */
    private ?StreamEndEvent $endEvent = null;

    /**
     * Tool calls collected during streaming.
     *
     * @var array<int, array{id: string, name: string, arguments: array<string, mixed>, result: string|null}>
     */
    private array $toolCalls = [];

    /**
     * Whether the stream has been iterated.
     */
    private bool $iterated = false;

    /**
     * @param  Generator<int, StreamEvent>  $stream  The underlying event generator.
     * @param  Closure|null  $onComplete  Optional callback when stream completes.
     */
    public function __construct(
        private Generator $stream,
        private ?Closure $onComplete = null,
    ) {}

    /**
     * Get the iterator for the stream events.
     *
     * @return Traversable<int, StreamEvent>
     */
    public function getIterator(): Traversable
    {
        $this->iterated = true;

        foreach ($this->stream as $event) {
            $this->events[] = $event;

            if ($event instanceof TextDeltaEvent) {
                $this->accumulatedText .= $event->text;
            }

            if ($event instanceof StreamEndEvent) {
                $this->endEvent = $event;
            }

            if ($event instanceof ToolCallStartEvent) {
                $this->toolCalls[] = [
                    'id' => $event->toolId,
                    'name' => $event->toolName,
                    'arguments' => $event->arguments,
                    'result' => null,
                ];
            }

            if ($event instanceof ToolCallEndEvent) {
                foreach ($this->toolCalls as $i => $call) {
                    if ($call['id'] === $event->toolId) {
                        $this->toolCalls[$i]['result'] = $event->result;
                        break;
                    }
                }
            }

            yield $event;
        }

        if ($this->onComplete !== null) {
            ($this->onComplete)($this);
        }
    }

    /**
     * Collect all events without manual iteration.
     *
     * Iterates through all events and returns self for chaining.
     */
    public function collect(): self
    {
        if (! $this->iterated) {
            iterator_to_array($this->getIterator());
        }

        return $this;
    }

    /**
     * Get the accumulated text from all TextDeltaEvents.
     *
     * Available after iteration or calling collect().
     */
    public function text(): string
    {
        return $this->accumulatedText;
    }

    /**
     * Get all collected events.
     *
     * Available after iteration or calling collect().
     *
     * @return array<int, StreamEvent>
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * Get the finish reason from the StreamEndEvent.
     *
     * Available after iteration or calling collect().
     */
    public function finishReason(): ?string
    {
        return $this->endEvent?->finishReason;
    }

    /**
     * Get usage statistics from the StreamEndEvent.
     *
     * Available after iteration or calling collect().
     *
     * @return array<string, int>
     */
    public function usage(): array
    {
        if ($this->endEvent === null) {
            return [];
        }

        return $this->endEvent->usage;
    }

    /**
     * Get total tokens used.
     *
     * Available after iteration or calling collect().
     */
    public function totalTokens(): int
    {
        return $this->endEvent?->totalTokens() ?? 0;
    }

    /**
     * Get prompt tokens used.
     *
     * Available after iteration or calling collect().
     */
    public function promptTokens(): int
    {
        return $this->endEvent?->promptTokens() ?? 0;
    }

    /**
     * Get completion tokens used.
     *
     * Available after iteration or calling collect().
     */
    public function completionTokens(): int
    {
        return $this->endEvent?->completionTokens() ?? 0;
    }

    /**
     * Get all tool calls made during the stream.
     *
     * Available after iteration or calling collect().
     *
     * @return array<int, array{id: string, name: string, arguments: array<string, mixed>, result: string|null}>
     */
    public function toolCalls(): array
    {
        return $this->toolCalls;
    }

    /**
     * Check if the stream had any errors.
     *
     * Available after iteration or calling collect().
     */
    public function hasErrors(): bool
    {
        foreach ($this->events as $event) {
            if ($event instanceof ErrorEvent) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all error events from the stream.
     *
     * Available after iteration or calling collect().
     *
     * @return array<int, ErrorEvent>
     */
    public function errors(): array
    {
        $errors = [];

        foreach ($this->events as $event) {
            if ($event instanceof ErrorEvent) {
                $errors[] = $event;
            }
        }

        return $errors;
    }

    /**
     * Convert the stream to an SSE StreamedResponse.
     *
     * Returns a Symfony StreamedResponse suitable for HTTP endpoints
     * that streams events in SSE format.
     *
     * @param  Closure|null  $onComplete  Callback when stream completes.
     * @param  array<string, string>  $headers  Additional headers to include.
     */
    public function toResponse(?Closure $onComplete = null, array $headers = []): StreamedResponse
    {
        $defaultHeaders = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];

        $mergedHeaders = array_merge($defaultHeaders, $headers);

        return new StreamedResponse(function () use ($onComplete): void {
            foreach ($this->getIterator() as $event) {
                echo $event->toSse();

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }

            if ($onComplete !== null) {
                $onComplete($this);
            }
        }, 200, $mergedHeaders);
    }
}
