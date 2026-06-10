<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Base class for execution lifecycle events.
 *
 * Provides shared broadcasting logic — events only broadcast
 * when a channel is provided, preventing silent errors.
 */
abstract class ExecutionEvent implements ShouldBroadcastNow
{
    public function __construct(
        public readonly ?int $executionId,
        protected readonly ?Channel $channel = null,
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly ?string $agentKey = null,
        public readonly ?string $traceId = null,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return $this->channel !== null ? [$this->channel] : [];
    }

    abstract public function broadcastAs(): string;

    public function broadcastWhen(): bool
    {
        return $this->channel !== null;
    }

    /**
     * The broadcast payload. Explicit so the wire shape is intentional and the
     * non-public channel is never serialized.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'executionId' => $this->executionId,
            'provider' => $this->provider,
            'model' => $this->model,
            'agentKey' => $this->agentKey,
            'traceId' => $this->traceId,
        ];
    }
}
