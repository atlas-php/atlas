<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Events\Concerns\CapsBroadcastPayload;
use Illuminate\Broadcasting\Channel;

/**
 * Fired when a queued Atlas execution fails after all retries.
 */
class ExecutionFailed extends ExecutionEvent
{
    use CapsBroadcastPayload;

    public function __construct(
        ?int $executionId,
        public readonly string $error,
        ?Channel $channel = null,
        ?string $provider = null,
        ?string $model = null,
        ?string $agentKey = null,
        ?string $traceId = null,
    ) {
        parent::__construct($executionId, $channel, $provider, $model, $agentKey, $traceId);
    }

    public function broadcastAs(): string
    {
        return 'ExecutionFailed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            ...parent::broadcastWith(),
            'error' => $this->capBroadcastPayload($this->error),
        ];
    }
}
