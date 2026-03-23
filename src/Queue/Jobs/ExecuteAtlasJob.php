<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Queue\Jobs;

use Atlasphp\Atlas\Events\ExecutionCompleted;
use Atlasphp\Atlas\Events\ExecutionFailed;
use Atlasphp\Atlas\Exceptions\MaxStepsExceededException;
use Atlasphp\Atlas\Queue\QueueableRequest;
use Atlasphp\Atlas\Responses\StreamResponse;
use Illuminate\Broadcasting\Channel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravel\SerializableClosure\SerializableClosure;
use Throwable;

/**
 * Single job class for all Atlas modality executions.
 *
 * Every pending request type implements QueueableRequest, which defines
 * how it serializes and rebuilds. This job handles the dispatch lifecycle,
 * persistence transitions, callbacks, and broadcasting.
 */
class ExecuteAtlasJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use TracksExecution;

    public int $tries;

    public int $backoff;

    public int $timeout;

    public ?SerializableClosure $thenCallback = null;

    public ?SerializableClosure $catchCallback = null;

    /**
     * @param  class-string<QueueableRequest>  $requestClass
     * @param  string  $terminal  Terminal method name (e.g. 'asText', 'asImage')
     * @param  array<string, mixed>  $payload  Serialized request state
     * @param  int|null  $executionId  Pre-created execution ID for persistence
     * @param  Channel|null  $broadcastChannel  Channel for broadcasting
     */
    public function __construct(
        public readonly string $requestClass,
        public readonly string $terminal,
        public readonly array $payload,
        public readonly ?int $executionId = null,
        public readonly ?Channel $broadcastChannel = null,
    ) {
        $this->tries = (int) config('atlas.queue.tries', 3);
        $this->backoff = (int) config('atlas.queue.backoff', 30);
        $this->timeout = (int) config('atlas.queue.timeout', 300);
    }

    public function handle(): void
    {
        // Transition queued → processing in persistence
        $this->transitionToProcessing();

        try {
            // Rebuild and execute — the request class knows how
            $result = ($this->requestClass)::executeFromPayload(
                payload: $this->payload,
                terminal: $this->terminal,
                executionId: $this->executionId,
                broadcastChannel: $this->broadcastChannel,
            );
        } catch (MaxStepsExceededException $e) {
            // Deterministic failure — retrying will produce the same loop.
            // Fail immediately instead of burning retries and API credits.
            $this->fail($e);

            return;
        }

        // StreamResponse must be consumed for broadcasting to fire.
        // All request classes return unconsumed streams; iteration here
        // handles consumption uniformly.
        if ($result instanceof StreamResponse) {
            foreach ($result as $chunk) {
                // Broadcasting happens inside the iterator
            }
        }

        // Fire success callback with the actual response
        if ($this->thenCallback !== null) {
            ($this->thenCallback->getClosure())($result);
        }

        // Broadcast completion
        event(new ExecutionCompleted(
            executionId: $this->executionId,
            channel: $this->broadcastChannel,
        ));
    }

    /**
     * Called by Laravel when all retries are exhausted.
     */
    public function failed(Throwable $exception): void
    {
        // Mark execution as failed in persistence
        $this->markExecutionFailed($exception);

        // Fire catch callback
        if ($this->catchCallback !== null) {
            ($this->catchCallback->getClosure())($exception);
        }

        // Broadcast failure
        event(new ExecutionFailed(
            executionId: $this->executionId,
            error: $exception->getMessage(),
            channel: $this->broadcastChannel,
        ));
    }
}
