<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Support;

use Atlasphp\Atlas\Agents\Jobs\InvokeAgent;
use Closure;
use Throwable;

/**
 * Wraps a queued agent job with a fluent configuration API.
 *
 * Provides then()/catch()/onQueue()/onConnection() methods
 * for configuring the queued agent execution. The job is dispatched
 * automatically when this object is destructed or explicitly via dispatch().
 */
final class QueuedAgentResponse
{
    private bool $dispatched = false;

    public function __construct(
        private readonly InvokeAgent $job,
    ) {}

    /**
     * Set a success callback for when the agent completes.
     *
     * @param  Closure(AgentResponse): void  $callback
     */
    public function then(Closure $callback): self
    {
        $this->job->then($callback);

        return $this;
    }

    /**
     * Set a failure callback for when the agent fails.
     *
     * @param  Closure(Throwable): void  $callback
     */
    public function catch(Closure $callback): self
    {
        $this->job->catch($callback);

        return $this;
    }

    /**
     * Set the queue to dispatch to.
     */
    public function onQueue(string $queue): self
    {
        $this->job->onQueue($queue);

        return $this;
    }

    /**
     * Set the connection to dispatch to.
     */
    public function onConnection(string $connection): self
    {
        $this->job->onConnection($connection);

        return $this;
    }

    /**
     * Set the job delay.
     */
    public function delay(\DateTimeInterface|\DateInterval|int $delay): self
    {
        $this->job->delay($delay);

        return $this;
    }

    /**
     * Dispatch the job to the queue.
     */
    public function dispatch(): void
    {
        if (! $this->dispatched) {
            $this->dispatched = true;
            dispatch($this->job);
        }
    }

    /**
     * Get the underlying job instance.
     */
    public function getJob(): InvokeAgent
    {
        return $this->job;
    }

    /**
     * Dispatch when destroyed if not already dispatched.
     */
    public function __destruct()
    {
        $this->dispatch();
    }
}
