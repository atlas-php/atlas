<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending\Concerns;

use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Queue\Jobs\ExecuteAtlasJob;
use Atlasphp\Atlas\Queue\PendingExecution;
use Illuminate\Broadcasting\Channel;

/**
 * Adds queue dispatch capability to pending request classes.
 *
 * Provides queue(), onQueue(), onConnection(), broadcastOn(), and the
 * internal dispatchToQueue() that terminal methods call when queued.
 */
trait HasQueueDispatch
{
    protected bool $queued = false;

    protected ?string $queueConnection = null;

    protected ?string $queueName = null;

    protected int $queueDelay = 0;

    protected ?int $queueTimeout = null;

    protected ?int $queueTries = null;

    protected ?int $queueBackoff = null;

    protected ?Channel $broadcastChannel = null;

    /**
     * Enable async dispatch. The next terminal method call will
     * dispatch to the queue instead of executing inline.
     */
    public function queue(?string $queue = null): static
    {
        $this->queued = true;

        if ($queue !== null) {
            $this->queueName = $queue;
        }

        return $this;
    }

    /**
     * Override queue connection for this call.
     */
    public function onConnection(string $connection): static
    {
        $this->queueConnection = $connection;

        return $this;
    }

    /**
     * Override queue name for this call.
     */
    public function onQueue(string $queue): static
    {
        $this->queueName = $queue;

        return $this;
    }

    /**
     * Set delay in seconds before the job is processed.
     */
    public function withDelay(int $seconds): static
    {
        $this->queueDelay = max(0, $seconds);

        return $this;
    }

    /**
     * Override job timeout in seconds for this call.
     *
     * Use this for long-running operations (e.g. video generation, complex agents)
     * that exceed the default 300-second timeout. Pass 0 for no timeout (Laravel
     * interprets timeout=0 as unlimited). Ensure your queue worker's --timeout
     * flag is also set high enough (Horizon: timeout config per queue).
     */
    public function withTimeout(int $seconds): static
    {
        $this->queueTimeout = max(0, $seconds);

        return $this;
    }

    /**
     * Override maximum retry attempts for this call.
     *
     * Set to 1 for expensive operations where retries waste API credits.
     */
    public function withTries(int $tries): static
    {
        $this->queueTries = max(1, $tries);

        return $this;
    }

    /**
     * Override retry backoff in seconds for this call.
     */
    public function withBackoff(int $seconds): static
    {
        $this->queueBackoff = max(0, $seconds);

        return $this;
    }

    /**
     * Set broadcast channel for queued execution.
     */
    public function broadcastOn(Channel $channel): static
    {
        $this->broadcastChannel = $channel;

        return $this;
    }

    /**
     * Dispatch this request to the queue.
     *
     * @param  string  $terminal  Terminal method name
     * @param  array<string, mixed>  $terminalArgs  Additional terminal arguments
     */
    protected function dispatchToQueue(string $terminal, array $terminalArgs = []): PendingExecution
    {
        $executionId = null;

        // If persistence enabled, create execution record directly so
        // consumer has an ID immediately for UI display. This bypasses
        // the scoped ExecutionService singleton to avoid contaminating
        // any active execution state (e.g., when queuing from inside a tool).
        if (config('atlas.persistence.enabled', false)) {
            /** @var class-string<Execution> $executionModel */
            $executionModel = config('atlas.persistence.models.execution', Execution::class);
            $meta = $this->getQueueMeta();

            $execution = $executionModel::create([
                'provider' => $this->resolveProviderKey(),
                'model' => $this->resolveModelKey(),
                'type' => $this->resolveExecutionType($terminal),
                'status' => ExecutionStatus::Queued,
                'metadata' => ! empty($meta) ? $meta : null,
            ]);

            $executionId = $execution->id;
        }

        // Merge terminal args into payload
        $payload = $this->toQueuePayload();
        $payload['_terminal_args'] = $terminalArgs;

        // Build the job
        $job = new ExecuteAtlasJob(
            requestClass: static::class,
            terminal: $terminal,
            payload: $payload,
            executionId: $executionId,
            broadcastChannel: $this->broadcastChannel,
        );

        // Apply queue routing
        $connection = $this->resolveQueueConnection();
        $queue = $this->resolveQueueName();

        if ($connection !== null) {
            $job->onConnection($connection);
        }

        $job->onQueue($queue);

        if ($this->queueDelay > 0) {
            $job->delay($this->queueDelay);
        }

        // Apply per-request job overrides
        if ($this->queueTimeout !== null) {
            $job->timeout = $this->queueTimeout;
        }

        if ($this->queueTries !== null) {
            $job->tries = $this->queueTries;
        }

        if ($this->queueBackoff !== null) {
            $job->backoff = $this->queueBackoff;
        }

        if (config('atlas.queue.after_commit', true)) {
            $job->afterCommit();
        }

        // Return PendingExecution — job is dispatched lazily via __destruct
        // so that then()/catch() callbacks can be set before dispatch
        return new PendingExecution(
            executionId: $executionId,
            job: $job,
            broadcastChannel: $this->broadcastChannel,
        );
    }

    /**
     * Resolve the effective queue connection.
     */
    protected function resolveQueueConnection(): ?string
    {
        return $this->queueConnection
            ?? config('atlas.queue.connection');
    }

    /**
     * Resolve the effective queue name.
     */
    protected function resolveQueueName(): string
    {
        return $this->queueName
            ?? config('atlas.queue.queue', 'default');
    }

    /**
     * Resolve the execution type from a terminal method name.
     */
    protected function resolveExecutionType(string $terminal): ExecutionType
    {
        return match ($terminal) {
            'asText' => ExecutionType::Text,
            'asStream', 'stream' => ExecutionType::Stream,
            'asStructured' => ExecutionType::Structured,
            'asImage' => ExecutionType::Image,
            'asAudio' => ExecutionType::Audio,
            'asVideo' => ExecutionType::Video,
            'asEmbeddings' => ExecutionType::Embed,
            'asModeration' => ExecutionType::Moderate,
            'asReranked' => ExecutionType::Rerank,
            default => throw new \InvalidArgumentException("Cannot resolve execution type for terminal: {$terminal}"),
        };
    }

    /**
     * Get provider key as string for execution record.
     */
    abstract protected function resolveProviderKey(): string;

    /**
     * Get model key as string for execution record.
     */
    abstract protected function resolveModelKey(): string;
}
