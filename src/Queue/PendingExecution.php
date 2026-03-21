<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Queue;

use Atlasphp\Atlas\Events\ExecutionQueued;
use Closure;
use Illuminate\Broadcasting\Channel;
use Laravel\SerializableClosure\SerializableClosure;

/**
 * Returned from queued terminal methods.
 *
 * Holds the execution ID for UI display and provides then/catch
 * callbacks that execute in the queue worker. The job is dispatched
 * lazily via __destruct so callbacks can be registered before dispatch.
 */
class PendingExecution
{
    protected bool $dispatched = false;

    public function __construct(
        public readonly ?int $executionId,
        protected readonly object $job,
        protected readonly ?Channel $broadcastChannel = null,
    ) {}

    /**
     * Register a callback for successful completion.
     * Receives the response from the terminal method.
     */
    public function then(Closure $callback): static
    {
        $this->job->thenCallback = new SerializableClosure($callback);

        return $this;
    }

    /**
     * Register a callback for failure (all retries exhausted).
     * Receives the Throwable.
     */
    public function catch(Closure $callback): static
    {
        $this->job->catchCallback = new SerializableClosure($callback);

        return $this;
    }

    /**
     * Dispatch the job immediately.
     * Normally called automatically via __destruct, but can be
     * called explicitly if needed.
     */
    public function dispatch(): static
    {
        if ($this->dispatched) {
            return $this;
        }

        $this->dispatched = true;

        dispatch($this->job);

        event(new ExecutionQueued(
            executionId: $this->executionId,
            channel: $this->broadcastChannel,
        ));

        return $this;
    }

    /**
     * Ensure the job is dispatched when the PendingExecution goes out of scope.
     * This allows then()/catch() to be set before dispatch.
     */
    public function __destruct()
    {
        try {
            $this->dispatch();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
