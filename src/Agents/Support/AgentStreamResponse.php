<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Support;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Events\AgentStreamChunk;
use Atlasphp\Atlas\Streaming\SseStreamFormatter;
use Atlasphp\Atlas\Streaming\StreamEventHelper;
use Atlasphp\Atlas\Streaming\VercelStreamProtocol;
use Closure;
use Generator;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use IteratorAggregate;
use Prism\Prism\Streaming\Events\ErrorEvent;
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
 * Supports per-event callbacks via each(), post-stream callbacks via then(),
 * error handling via onError(), stream replay after consumption, and
 * inline broadcasting via broadcast()/broadcastNow().
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
     * Per-event callback.
     *
     * @var Closure(StreamEvent, self): void|null
     */
    private ?Closure $eachCallback = null;

    /**
     * Error event callback.
     *
     * @var Closure(ErrorEvent, self): void|null
     */
    private ?Closure $errorCallback = null;

    /**
     * Broadcast request ID for inline broadcasting.
     */
    private ?string $broadcastRequestId = null;

    /**
     * Whether to broadcast synchronously (broadcastNow) or via queue.
     */
    private bool $broadcastSync = false;

    /**
     * Whether inline broadcasting is enabled.
     */
    private bool $broadcastEnabled = false;

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
     * On replay (after consumption), yields from collected events without
     * firing each(), then(), or onError() callbacks.
     *
     * @return Traversable<int, StreamEvent>
     */
    public function getIterator(): Traversable
    {
        if ($this->consumed) {
            yield from $this->collectedEvents;

            return;
        }

        foreach ($this->stream as $event) {
            $this->collectedEvents[] = $event;
            $this->fireEachCallback($event);
            $this->fireErrorCallback($event);
            $this->fireBroadcastCallback($event);
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
     * Register a callback to execute on each stream event.
     *
     * Callback is NOT fired on replay.
     *
     * ```php
     * return Atlas::agent('writer')->stream($input)
     *     ->each(fn(StreamEvent $e) => Log::info($e->eventKey()));
     * ```
     *
     * @param  Closure(StreamEvent, self): void  $callback
     */
    public function each(Closure $callback): self
    {
        $this->eachCallback = $callback;

        return $this;
    }

    /**
     * Register a callback for error events.
     *
     * Callback is NOT fired on replay.
     *
     * @param  Closure(ErrorEvent, self): void  $callback
     */
    public function onError(Closure $callback): self
    {
        $this->errorCallback = $callback;

        return $this;
    }

    /**
     * Enable queued broadcasting during stream iteration.
     *
     * Broadcasts each stream event as an AgentStreamChunk via the queue.
     *
     * ```php
     * return Atlas::agent('writer')->stream($input)->broadcast($requestId);
     * ```
     */
    public function broadcast(?string $requestId = null): self
    {
        $this->broadcastEnabled = true;
        $this->broadcastSync = false;
        $this->broadcastRequestId = $requestId ?? bin2hex(random_bytes(16));

        return $this;
    }

    /**
     * Enable synchronous broadcasting during stream iteration.
     *
     * Broadcasts each stream event as an AgentStreamChunk immediately (no queue).
     *
     * ```php
     * return Atlas::agent('writer')->stream($input)->broadcastNow($requestId);
     * ```
     */
    public function broadcastNow(?string $requestId = null): self
    {
        $this->broadcastEnabled = true;
        $this->broadcastSync = true;
        $this->broadcastRequestId = $requestId ?? bin2hex(random_bytes(16));

        return $this;
    }

    /**
     * Get the broadcast request ID.
     */
    public function broadcastRequestId(): ?string
    {
        return $this->broadcastRequestId;
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
     * Callback is NOT fired on replay.
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
     * Fire the each callback if set.
     */
    private function fireEachCallback(StreamEvent $event): void
    {
        if ($this->eachCallback !== null) {
            ($this->eachCallback)($event, $this);
        }
    }

    /**
     * Fire the error callback if the event is an ErrorEvent.
     */
    private function fireErrorCallback(StreamEvent $event): void
    {
        if ($this->errorCallback !== null && $event instanceof ErrorEvent) {
            ($this->errorCallback)($event, $this);
        }
    }

    /**
     * Broadcast the event if inline broadcasting is enabled.
     */
    private function fireBroadcastCallback(StreamEvent $event): void
    {
        if (! $this->broadcastEnabled) {
            return;
        }

        if (config('atlas.events.enabled', true) === false) {
            return;
        }

        $chunk = new AgentStreamChunk(
            agentKey: $this->agentKey(),
            requestId: $this->broadcastRequestId ?? '',
            type: $event->eventKey(),
            delta: StreamEventHelper::extractDelta($event),
            metadata: $event->toArray(),
        );

        if ($this->broadcastSync) {
            Bus::dispatchSync(new BroadcastEvent(clone $chunk));
        } else {
            event($chunk);
        }
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
            $aborted = false;

            foreach ($this->stream as $event) {
                $this->collectedEvents[] = $event;
                $this->fireEachCallback($event);
                $this->fireErrorCallback($event);
                $this->fireBroadcastCallback($event);

                if (connection_aborted()) {
                    $aborted = true;

                    break;
                }

                echo $formatter->format($event);

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }

            if (! $aborted) {
                echo $formatter->done();

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }

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
                $this->fireEachCallback($event);
                $this->fireErrorCallback($event);
                $this->fireBroadcastCallback($event);

                if (connection_aborted()) {
                    break;
                }

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
        }, 200, VercelStreamProtocol::headers());
    }
}
