<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Jobs;

use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Agents\Support\AgentContext;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Closure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravel\SerializableClosure\SerializableClosure;
use Throwable;

/**
 * Queueable job that executes an agent asynchronously.
 *
 * Resolves the agent by key, rebuilds context from serialized data,
 * and executes via the standard AgentExecutor pipeline.
 */
class InvokeAgent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The success callback (stored as serializable closure).
     */
    public ?SerializableClosure $thenCallback = null;

    /**
     * The failure callback (stored as serializable closure).
     */
    public ?SerializableClosure $catchCallback = null;

    /**
     * @param  string  $agentKey  The agent registry key.
     * @param  string  $input  The user input message.
     * @param  array<string, mixed>  $serializedContext  Serialized AgentContext data from toArray().
     */
    public function __construct(
        public readonly string $agentKey,
        public readonly string $input,
        public readonly array $serializedContext,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AgentResolver $resolver, AgentExecutorContract $executor): void
    {
        $agent = $resolver->resolve($this->agentKey);
        $context = AgentContext::fromArray($this->serializedContext);

        try {
            $response = $executor->execute($agent, $this->input, $context);

            if ($this->thenCallback !== null) {
                ($this->thenCallback)($response);
            }
        } catch (Throwable $e) {
            if ($this->catchCallback !== null) {
                ($this->catchCallback)($e);

                return;
            }

            throw $e;
        }
    }

    /**
     * Set a success callback.
     *
     * @param  Closure(AgentResponse): void  $callback
     */
    public function then(Closure $callback): self
    {
        $this->thenCallback = new SerializableClosure($callback);

        return $this;
    }

    /**
     * Set a failure callback.
     *
     * @param  Closure(Throwable): void  $callback
     */
    public function catch(Closure $callback): self
    {
        $this->catchCallback = new SerializableClosure($callback);

        return $this;
    }
}
