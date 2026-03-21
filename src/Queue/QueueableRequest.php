<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Queue;

use Illuminate\Broadcasting\Channel;

/**
 * Contract for pending requests that support queue dispatch.
 *
 * Every pending request class implements this to define how it serializes
 * for the queue and how it rebuilds and executes in the worker.
 */
interface QueueableRequest
{
    /**
     * Serialize this request into a payload that survives the queue.
     *
     * @return array<string, mixed>
     */
    public function toQueuePayload(): array;

    /**
     * Rebuild the request from a payload and execute the given terminal.
     *
     * @param  array<string, mixed>  $payload
     * @param  string  $terminal  Terminal method name (e.g. 'asText', 'asImage')
     * @param  int|null  $executionId  Pre-created execution ID for persistence
     * @param  Channel|null  $broadcastChannel  Channel for broadcasting
     */
    public static function executeFromPayload(
        array $payload,
        string $terminal,
        ?int $executionId = null,
        ?Channel $broadcastChannel = null,
    ): mixed;
}
