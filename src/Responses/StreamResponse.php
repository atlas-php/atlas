<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses;

use Atlasphp\Atlas\Enums\ChunkType;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Events\StreamChunkReceived;
use Atlasphp\Atlas\Events\StreamCompleted;
use Atlasphp\Atlas\Events\StreamStarted;
use Atlasphp\Atlas\Events\StreamThinkingReceived;
use Atlasphp\Atlas\Events\StreamToolCallReceived;
use Atlasphp\Atlas\Messages\ToolCall;
use Closure;
use Generator;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Support\Responsable;
use IteratorAggregate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A streaming response that yields chunks with optional SSE delivery, broadcasting, and callbacks.
 *
 * Implements Responsable for direct SSE delivery from Laravel routes.
 * Broadcasting and callbacks fire automatically during chunk iteration.
 *
 * @implements IteratorAggregate<int, StreamChunk>
 */
class StreamResponse implements IteratorAggregate, Responsable
{
    protected string $accumulatedText = '';

    protected string $accumulatedReasoning = '';

    protected ?Usage $usage = null;

    protected ?FinishReason $finishReason = null;

    /** @var array<int, ToolCall> */
    protected array $toolCalls = [];

    protected ?Channel $broadcastChannel = null;

    protected ?Closure $onChunkCallback = null;

    /** @var array<int, Closure> */
    protected array $thenCallbacks = [];

    /**
     * @param  iterable<StreamChunk>  $source
     */
    public function __construct(
        protected readonly iterable $source,
    ) {}

    // ─── Configuration (chainable) ──────────────────────────────────

    /**
     * Broadcast chunks to a Laravel broadcasting channel during consumption.
     *
     * Works with Reverb, Pusher, Ably, or any configured broadcasting driver.
     * Broadcasting happens automatically as chunks are iterated.
     */
    public function broadcastOn(Channel $channel): static
    {
        $this->broadcastChannel = $channel;

        return $this;
    }

    /**
     * Register a callback invoked for each chunk during iteration.
     */
    public function onChunk(Closure $callback): static
    {
        $this->onChunkCallback = $callback;

        return $this;
    }

    /**
     * Register a callback invoked after the stream completes.
     * Multiple callbacks can be registered — they fire in order.
     */
    public function then(Closure $callback): static
    {
        $this->thenCallbacks[] = $callback;

        return $this;
    }

    // ─── Iteration (core loop — everything happens here) ────────────

    /**
     * Iterate through stream chunks. This stream may only be iterated once.
     * Calling toResponse() after manual iteration will produce an empty response.
     *
     * @return Generator<int, StreamChunk>
     */
    public function getIterator(): Generator
    {
        // Broadcast stream start
        if ($this->broadcastChannel !== null) {
            broadcast(new StreamStarted(channel: $this->broadcastChannel));
        }

        try {
            foreach ($this->source as $chunk) {
                // 1. Accumulate
                if ($chunk->text !== null) {
                    $this->accumulatedText .= $chunk->text;
                }

                if ($chunk->reasoning !== null) {
                    $this->accumulatedReasoning .= $chunk->reasoning;
                }

                if ($chunk->toolCalls !== []) {
                    $this->toolCalls = array_merge($this->toolCalls, $chunk->toolCalls);
                }

                if ($chunk->usage !== null) {
                    $this->usage = $chunk->usage;
                }

                if ($chunk->finishReason !== null) {
                    $this->finishReason = $chunk->finishReason;
                }

                // 2. Per-chunk callback
                if ($this->onChunkCallback !== null) {
                    ($this->onChunkCallback)($chunk);
                }

                // 3. Broadcast chunk
                if ($this->broadcastChannel !== null) {
                    $this->broadcastChunk($chunk);
                }

                // 4. Yield
                yield $chunk;
            }
        } catch (\Throwable $e) {
            // Broadcast error so frontend clients don't stay in "typing..." state
            if ($this->broadcastChannel !== null) {
                broadcast(new StreamCompleted(
                    channel: $this->broadcastChannel,
                    text: $this->accumulatedText,
                    usage: null,
                    finishReason: null,
                    error: $e->getMessage(),
                ));
            }

            throw $e;
        }

        // 5. Broadcast completion
        if ($this->broadcastChannel !== null) {
            broadcast(new StreamCompleted(
                channel: $this->broadcastChannel,
                text: $this->accumulatedText,
                usage: $this->serializeUsage(),
                finishReason: $this->finishReason,
            ));
        }

        // 6. Post-stream callbacks
        foreach ($this->thenCallbacks as $callback) {
            $callback($this);
        }
    }

    // ─── Broadcasting ───────────────────────────────────────────────

    protected function broadcastChunk(StreamChunk $chunk): void
    {
        match ($chunk->type) {
            ChunkType::Text => broadcast(new StreamChunkReceived(
                channel: $this->broadcastChannel,
                text: $chunk->text ?? '',
            )),
            ChunkType::Thinking => broadcast(new StreamThinkingReceived(
                channel: $this->broadcastChannel,
                text: $chunk->reasoning ?? '',
            )),
            ChunkType::ToolCall => broadcast(new StreamToolCallReceived(
                channel: $this->broadcastChannel,
                toolCalls: array_map($this->serializeToolCall(...), $chunk->toolCalls),
            )),
            ChunkType::Done => null, // StreamCompleted fires after the loop
        };
    }

    // ─── SSE Response (Responsable) ─────────────────────────────────

    /**
     * Convert the stream into an SSE HTTP response.
     *
     * When returned from a route, Laravel calls this automatically.
     * Each chunk is sent as a named SSE event with JSON data.
     * If broadcastOn() was called, chunks are also broadcast simultaneously.
     */
    public function toResponse($request): StreamedResponse
    {
        return new StreamedResponse(function () {
            foreach ($this as $chunk) {
                $this->sendSseEvent($chunk);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    protected function sendSseEvent(StreamChunk $chunk): void
    {
        $data = match ($chunk->type) {
            ChunkType::Text => [
                'type' => 'chunk',
                'text' => $chunk->text,
            ],
            ChunkType::Thinking => [
                'type' => 'thinking',
                'text' => $chunk->reasoning,
            ],
            ChunkType::ToolCall => [
                'type' => 'tool_call',
                'toolCalls' => array_map($this->serializeToolCall(...), $chunk->toolCalls),
            ],
            ChunkType::Done => [
                'type' => 'done',
                'text' => $this->accumulatedText,
                'usage' => $this->serializeUsage(),
            ],
        };

        echo "event: {$data['type']}\n";
        echo 'data: '.json_encode($data, JSON_THROW_ON_ERROR)."\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }

    // ─── Accessors (available after iteration) ──────────────────────

    /**
     * Get the accumulated text after iteration.
     */
    public function getText(): string
    {
        return $this->accumulatedText;
    }

    /**
     * Get the accumulated reasoning/thinking content after iteration.
     */
    public function getReasoning(): string
    {
        return $this->accumulatedReasoning;
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

    /**
     * Get all accumulated tool calls after iteration.
     *
     * @return array<int, ToolCall>
     */
    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    // ─── Private Helpers ────────────────────────────────────────────

    /**
     * Serialize a ToolCall for broadcast and SSE delivery.
     *
     * @return array<string, mixed>
     */
    private function serializeToolCall(ToolCall $tc): array
    {
        return ['id' => $tc->id, 'name' => $tc->name, 'arguments' => $tc->arguments];
    }

    /**
     * Serialize usage data to a simple array for broadcast and SSE delivery.
     *
     * @return array<string, int>|null
     */
    private function serializeUsage(): ?array
    {
        if ($this->usage === null) {
            return null;
        }

        return [
            'input_tokens' => $this->usage->inputTokens,
            'output_tokens' => $this->usage->outputTokens,
        ];
    }
}
