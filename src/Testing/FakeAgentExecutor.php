<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Streaming\StreamResponse;
use Atlasphp\Atlas\Testing\Support\FakeResponseSequence;
use Atlasphp\Atlas\Testing\Support\RecordedRequest;
use Prism\Prism\Contracts\Schema;
use Prism\Prism\Enums\StructuredMode;
use RuntimeException;

/**
 * Fake agent executor for testing.
 *
 * Records all execution requests and returns configured fake responses,
 * enabling unit testing of agent interactions without API calls.
 */
class FakeAgentExecutor implements AgentExecutorContract
{
    /**
     * @var array<int, RecordedRequest>
     */
    private array $recordedRequests = [];

    /**
     * @var array<string, FakeResponseSequence>
     */
    private array $responseSequences = [];

    /**
     * Default response sequence for unmatched agents.
     */
    private ?FakeResponseSequence $defaultSequence = null;

    /**
     * Whether to prevent requests to agents without configured responses.
     */
    private bool $preventStrayRequests = false;

    /**
     * The real executor to fall back to if allowed.
     */
    private ?AgentExecutorContract $realExecutor = null;

    /**
     * Configure a response sequence for a specific agent.
     *
     * @param  string  $agentKey  The agent key.
     * @param  FakeResponseSequence  $sequence  The response sequence.
     */
    public function addSequence(string $agentKey, FakeResponseSequence $sequence): self
    {
        $this->responseSequences[$agentKey] = $sequence;

        return $this;
    }

    /**
     * Configure the default response sequence for unmatched agents.
     */
    public function setDefaultSequence(FakeResponseSequence $sequence): self
    {
        $this->defaultSequence = $sequence;

        return $this;
    }

    /**
     * Prevent requests to agents without configured responses.
     */
    public function preventStrayRequests(bool $prevent = true): self
    {
        $this->preventStrayRequests = $prevent;

        return $this;
    }

    /**
     * Set the real executor for fallback.
     */
    public function setRealExecutor(AgentExecutorContract $executor): self
    {
        $this->realExecutor = $executor;

        return $this;
    }

    /**
     * Get all recorded requests.
     *
     * @return array<int, RecordedRequest>
     */
    public function recorded(): array
    {
        return $this->recordedRequests;
    }

    /**
     * Get recorded requests for a specific agent.
     *
     * @return array<int, RecordedRequest>
     */
    public function recordedFor(string $agentKey): array
    {
        return array_filter(
            $this->recordedRequests,
            fn (RecordedRequest $r): bool => $r->agentKey() === $agentKey
        );
    }

    /**
     * Reset all recorded requests and sequences.
     */
    public function reset(): self
    {
        $this->recordedRequests = [];

        foreach ($this->responseSequences as $sequence) {
            $sequence->reset();
        }

        $this->defaultSequence?->reset();

        return $this;
    }

    public function execute(
        AgentContract $agent,
        string $input,
        ?ExecutionContext $context = null,
        ?Schema $schema = null,
        ?array $retry = null,
        ?StructuredMode $structuredMode = null,
    ): AgentResponse {
        $response = $this->getResponse($agent->key());

        // Handle throwables
        if ($response instanceof \Throwable) {
            $this->recordRequest($agent, $input, $context, $schema, AgentResponse::empty());
            throw $response;
        }

        // Handle StreamResponse (shouldn't happen in execute, but return empty)
        if ($response instanceof StreamResponse) {
            $response = AgentResponse::empty();
        }

        $this->recordRequest($agent, $input, $context, $schema, $response);

        return $response;
    }

    public function stream(
        AgentContract $agent,
        string $input,
        ?ExecutionContext $context = null,
        ?array $retry = null,
    ): StreamResponse {
        $response = $this->getResponse($agent->key());

        // Handle throwables
        if ($response instanceof \Throwable) {
            $this->recordRequest($agent, $input, $context, null, AgentResponse::empty());
            throw $response;
        }

        // Handle AgentResponse by converting to StreamResponse
        if ($response instanceof AgentResponse) {
            $response = $this->convertToStreamResponse($response);
        }

        $this->recordRequest($agent, $input, $context, null, AgentResponse::empty());

        return $response;
    }

    /**
     * Get the next response for an agent.
     */
    private function getResponse(string $agentKey): AgentResponse|StreamResponse|\Throwable
    {
        // Check for agent-specific sequence
        if (isset($this->responseSequences[$agentKey])) {
            return $this->responseSequences[$agentKey]->next();
        }

        // Check for default sequence
        if ($this->defaultSequence !== null) {
            return $this->defaultSequence->next();
        }

        // Check if stray requests should be prevented
        if ($this->preventStrayRequests) {
            throw new RuntimeException(
                "Unexpected agent execution: '{$agentKey}'. Configure a response or disable stray request prevention."
            );
        }

        // Check if we can fall back to real executor
        if ($this->realExecutor !== null) {
            throw new RuntimeException(
                'Cannot fall back to real executor within FakeAgentExecutor. Configure a response instead.'
            );
        }

        // Return empty response
        return AgentResponse::empty();
    }

    /**
     * Record an agent request.
     */
    private function recordRequest(
        AgentContract $agent,
        string $input,
        ?ExecutionContext $context,
        ?Schema $schema,
        AgentResponse $response,
    ): void {
        $this->recordedRequests[] = new RecordedRequest(
            agent: $agent,
            input: $input,
            context: $context,
            schema: $schema,
            response: $response,
            timestamp: time(),
        );
    }

    /**
     * Convert an AgentResponse to a StreamResponse.
     */
    private function convertToStreamResponse(AgentResponse $response): StreamResponse
    {
        $text = $response->text ?? '';

        return \Atlasphp\Atlas\Testing\Support\StreamEventFactory::fromText($text);
    }
}
