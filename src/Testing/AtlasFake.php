<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing;

use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Testing\Concerns\HasFakeAssertions;
use Atlasphp\Atlas\Testing\Support\FakeResponseSequence;
use Atlasphp\Atlas\Testing\Support\RecordedRequest;
use Illuminate\Contracts\Container\Container;

/**
 * Main fake manager for Atlas testing.
 *
 * Provides a Laravel Http::fake()-style API for mocking Atlas agent
 * execution during unit tests.
 */
class AtlasFake
{
    use HasFakeAssertions;

    private FakeAgentExecutor $fakeExecutor;

    private ?AgentExecutorContract $originalExecutor = null;

    public function __construct(
        private readonly Container $container,
    ) {
        $this->fakeExecutor = new FakeAgentExecutor;
    }

    /**
     * Configure a fake response for a specific agent.
     *
     * @param  string  $agentKey  The agent key.
     * @param  AgentResponse|null  $response  Optional immediate response.
     */
    public function response(string $agentKey, ?AgentResponse $response = null): PendingFakeRequest|self
    {
        if ($response !== null) {
            $this->registerSequence($agentKey, (new FakeResponseSequence)->push($response));

            return $this;
        }

        return new PendingFakeRequest($this, $agentKey);
    }

    /**
     * Configure a sequence of responses for any agent.
     *
     * @param  array<int, AgentResponse>  $responses
     */
    public function sequence(array $responses): self
    {
        $sequence = new FakeResponseSequence;

        foreach ($responses as $response) {
            $sequence->push($response);
        }

        $this->fakeExecutor->setDefaultSequence($sequence);

        return $this;
    }

    /**
     * Prevent requests to agents without configured responses.
     */
    public function preventStrayRequests(): self
    {
        $this->fakeExecutor->preventStrayRequests(true);

        return $this;
    }

    /**
     * Allow stray requests (return empty responses).
     */
    public function allowStrayRequests(): self
    {
        $this->fakeExecutor->preventStrayRequests(false);

        return $this;
    }

    /**
     * Get all recorded requests.
     *
     * @return array<int, RecordedRequest>
     */
    public function recorded(): array
    {
        return $this->fakeExecutor->recorded();
    }

    /**
     * Get recorded requests for a specific agent.
     *
     * @return array<int, RecordedRequest>
     */
    public function recordedFor(string $agentKey): array
    {
        return $this->fakeExecutor->recordedFor($agentKey);
    }

    /**
     * Reset all recorded requests and response sequences.
     */
    public function reset(): self
    {
        $this->fakeExecutor->reset();

        return $this;
    }

    /**
     * Activate the fake by swapping the container binding.
     */
    public function activate(): self
    {
        // Store the original executor
        if ($this->container->bound(AgentExecutorContract::class)) {
            $this->originalExecutor = $this->container->make(AgentExecutorContract::class);
        }

        // Bind the fake executor
        $this->container->instance(AgentExecutorContract::class, $this->fakeExecutor);

        return $this;
    }

    /**
     * Restore the original executor.
     */
    public function restore(): void
    {
        if ($this->originalExecutor !== null) {
            $this->container->instance(AgentExecutorContract::class, $this->originalExecutor);
            $this->originalExecutor = null;
        }
    }

    /**
     * Get the fake executor instance.
     */
    public function getExecutor(): FakeAgentExecutor
    {
        return $this->fakeExecutor;
    }

    /**
     * Register a response sequence for an agent.
     *
     * @internal Used by PendingFakeRequest.
     */
    public function registerSequence(string $agentKey, FakeResponseSequence $sequence): void
    {
        $this->fakeExecutor->addSequence($agentKey, $sequence);
    }

    /**
     * Get all recorded requests for assertions.
     *
     * @return array<int, RecordedRequest>
     */
    protected function getRecordedRequests(): array
    {
        return $this->fakeExecutor->recorded();
    }
}
