<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing;

use Atlasphp\Atlas\Testing\Support\FakeResponseSequence;
use Prism\Prism\Text\Response as PrismResponse;
use Throwable;

/**
 * Fluent builder for configuring fake responses.
 *
 * Provides a clean API for setting up response sequences and
 * conditions for specific agents during testing. Uses Prism's
 * native Response objects.
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
     * Return a specific Prism response.
     */
    public function return(PrismResponse $response): AtlasFake
    {
        $this->sequence->push($response);

        return $this->complete();
    }

    /**
     * Return a sequence of Prism responses.
     *
     * @param  array<int, PrismResponse>  $responses
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
    public function whenEmpty(PrismResponse|Throwable $response): self
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
