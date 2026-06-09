<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Events\Concerns\BroadcastsOnChannel;
use Atlasphp\Atlas\Events\Concerns\CapsBroadcastPayload;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Broadcast event fired for tool call chunks during streaming.
 */
class StreamToolCallReceived implements ShouldBroadcastNow
{
    use BroadcastsOnChannel;
    use CapsBroadcastPayload;

    /**
     * @param  array<int, array<string, mixed>>  $toolCalls
     */
    public function __construct(
        protected Channel $channel,
        public readonly array $toolCalls,
    ) {}

    public function broadcastAs(): string
    {
        return 'StreamToolCallReceived';
    }

    /**
     * Cap string values in each streamed tool-call chunk (e.g. a large partial
     * arguments string) to the configured broadcast limit, like the other
     * tool-call events.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'toolCalls' => array_map(
                fn (array $call): array => array_map(
                    fn (mixed $value): mixed => is_string($value) ? $this->capBroadcastPayload($value) : $value,
                    $call,
                ),
                $this->toolCalls,
            ),
        ];
    }
}
