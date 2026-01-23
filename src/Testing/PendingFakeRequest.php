<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing;

use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Streaming\StreamResponse;
use Atlasphp\Atlas\Testing\Support\FakeResponseSequence;
use Throwable;

/**
 * Fluent builder for configuring fake responses.
 *
 * Provides a clean API for setting up response sequences and
 * conditions for specific agents during testing.
 */
final class PendingFakeRequest
{
    private FakeResponseSequence $sequence;

    public function __construct(
        private readonly AtlasFake $fake,
        private readonly string $agentKey,
    ) {
        $this->sequence = new FakeResponseSequence;
    }

    /**
     * Return a specific response.
     */
    public function return(AgentResponse|StreamResponse $response): AtlasFake
    {
        $this->sequence->push($response);

        return $this->complete();
    }

    /**
     * Return a sequence of responses.
     *
     * @param  array<int, AgentResponse|StreamResponse>  $responses
     */
    public function returnSequence(array $responses): AtlasFake
    {
        foreach ($responses as $response) {
            $this->sequence->push($response);
        }

        return $this->complete();
    }

    /**
     * Throw an exception when this agent is called.
     */
    public function throw(Throwable $exception): AtlasFake
    {
        $this->sequence->push($exception);

        return $this->complete();
    }

    /**
     * Set the response to return when the sequence is exhausted.
     */
    public function whenEmpty(AgentResponse|StreamResponse|Throwable $response): self
    {
        $this->sequence->whenEmpty($response);

        return $this;
    }

    /**
     * Complete configuration and register with the fake.
     */
    private function complete(): AtlasFake
    {
        $this->fake->registerSequence($this->agentKey, $this->sequence);

        return $this->fake;
    }
}
