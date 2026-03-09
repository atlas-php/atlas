<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Broadcast event for individual stream chunks.
 *
 * Implements ShouldBroadcast for real-time WebSocket delivery
 * of streaming AI responses to frontend clients.
 * The metadata field carries full event data from Prism's toArray().
 *
 * Channel convention: atlas.agent.{agentKey}.{requestId}
 */
class AgentStreamChunk implements ShouldBroadcast
{
    use Dispatchable;

    /**
     * @param  string  $agentKey  The agent registry key.
     * @param  string  $requestId  Unique request identifier for channel scoping.
     * @param  string  $type  The event type from Prism's eventKey() (e.g., 'text_delta', 'stream_start').
     * @param  string|null  $delta  The text delta content (for text_delta and thinking_delta events).
     * @param  array<string, mixed>  $metadata  Full event data from Prism's toArray().
     */
    public function __construct(
        public readonly string $agentKey,
        public readonly string $requestId,
        public readonly string $type,
        public readonly ?string $delta = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("atlas.agent.{$this->agentKey}.{$this->requestId}"),
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'atlas.stream.chunk';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'delta' => $this->delta,
            'metadata' => $this->metadata,
        ];
    }
}
