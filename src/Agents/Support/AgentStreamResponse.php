<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Support;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Streaming\SseStreamFormatter;
use Atlasphp\Atlas\Streaming\VercelStreamProtocol;
use Closure;
use Generator;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use IteratorAggregate;
use Prism\Prism\Streaming\Events\StreamEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Traversable;

/**
 * Wrapper for agent streaming responses.
 *
 * Provides agent context alongside stream events. Implements
 * IteratorAggregate for seamless foreach iteration and Responsable
 * for direct return from Laravel controllers as SSE.
 *
 * @implements IteratorAggregate<int, StreamEvent>
 */
final class AgentStreamResponse implements IteratorAggregate, Responsable
{
    /**
     * @var array<int, StreamEvent>
     */
    private array $collectedEvents = [];

    private bool $consumed = false;

    private bool $useVercelProtocol = false;

    private bool $thenCallbackFired = false;

    /**
     * Post-stream callback.
     *
     * @var Closure(self): void|null
     */
    private ?Closure $thenCallback = null;

    /**
     * @param  Generator<int, StreamEvent>  $stream
     */
    public function __construct(
        private Generator $stream,
        public readonly AgentContract $agent,
        public readonly string $input,
        public readonly ?string $systemPrompt,
        public readonly AgentContext $context,
    ) {}

    /**
     * Get the iterator for foreach iteration.
     *
     * Yields stream events while collecting them for post-iteration access.
     *
     * @return Traversable<int, StreamEvent>
     */
    public function getIterator(): Traversable
    {
        foreach ($this->stream as $event) {
            $this->collectedEvents[] = $event;
            yield $event;
        }
        $this->consumed = true;

        $this->fireThenCallback();
    }

    /**
     * Convert to a Laravel HTTP response (SSE by default).
     *
     * Implements Responsable interface for direct return from controllers:
     * ```php
     * return Atlas::agent('my-agent')->stream($input);
     * ```
     *
     * @param  Request  $request
     */
    public function toResponse($request): StreamedResponse
    {
        if ($this->useVercelProtocol) {
            return $this->createVercelStreamResponse();
        }

        return $this->createSseStreamResponse();
    }

    /**
     * Return a Vercel AI SDK protocol StreamedResponse.
     *
     * ```php
     * return Atlas::agent('my-agent')->stream($input)->asVercelStream();
     * ```
     */
    public function asVercelStream(): self
    {
        $this->useVercelProtocol = true;

        return $this;
    }

    /**
     * Collect and return the full text after consuming the stream.
     *
     * If the stream hasn't been consumed yet, consumes it first.
     */
    public function text(): string
    {
        if (! $this->consumed) {
            // Consume the stream
            foreach ($this as $event) {
                // collecting via getIterator
            }
        }

        $text = '';
        foreach ($this->collectedEvents as $event) {
            if ($event instanceof TextDeltaEvent) {
                $text .= $event->delta;
            }
        }

        return $text;
    }

    /**
     * Register a callback to execute after the stream is consumed.
     *
     * @param  Closure(self): void  $callback
     */
    public function then(Closure $callback): self
    {
        $this->thenCallback = $callback;

        return $this;
    }

    /**
     * Get the agent key.
     */
    public function agentKey(): string
    {
        return $this->agent->key();
    }

    /**
     * Get the agent name.
     */
    public function agentName(): string
    {
        return $this->agent->name();
    }

    /**
     * Get the agent description.
     */
    public function agentDescription(): ?string
    {
        return $this->agent->description();
    }

    /**
     * Get the pipeline metadata from context.
     *
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->context->metadata;
    }

    /**
     * Get the variables used for prompt interpolation.
     *
     * @return array<string, mixed>
     */
    public function variables(): array
    {
        return $this->context->variables;
    }

    /**
     * Get all collected events after stream consumption.
     *
     * @return array<int, StreamEvent>
     */
    public function events(): array
    {
        return $this->collectedEvents;
    }

    /**
     * Check if the stream has been fully consumed.
     */
    public function isConsumed(): bool
    {
        return $this->consumed;
    }

    /**
     * Fire the then callback once, guarding against double invocation.
     */
    private function fireThenCallback(): void
    {
        if ($this->thenCallback !== null && ! $this->thenCallbackFired) {
            $this->thenCallbackFired = true;
            ($this->thenCallback)($this);
        }
    }

    /**
     * Create an SSE StreamedResponse.
     */
    private function createSseStreamResponse(): StreamedResponse
    {
        $formatter = new SseStreamFormatter;

        return new StreamedResponse(function () use ($formatter): void {
            foreach ($this->stream as $event) {
                $this->collectedEvents[] = $event;
                echo $formatter->format($event);

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }

            echo $formatter->done();

            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();

            $this->consumed = true;

            $this->fireThenCallback();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Create a Vercel AI SDK protocol StreamedResponse.
     */
    private function createVercelStreamResponse(): StreamedResponse
    {
        $protocol = new VercelStreamProtocol;

        return new StreamedResponse(function () use ($protocol): void {
            foreach ($this->stream as $event) {
                $this->collectedEvents[] = $event;
                $formatted = $protocol->format($event);

                if ($formatted !== null) {
                    echo $formatted;

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            }

            $this->consumed = true;

            $this->fireThenCallback();
        }, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
