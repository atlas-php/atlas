<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Testing\Support\FakeResponseSequence;
use Atlasphp\Atlas\Testing\Support\RecordedRequest;
use Generator;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\StreamEvent;
use Prism\Prism\Streaming\Events\StreamStartEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Text\Response as PrismResponse;
use Prism\Prism\ValueObjects\Usage;
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
        ExecutionContext $context,
    ): PrismResponse {
        $response = $this->getResponse($agent->key());

        // Handle throwables
        if ($response instanceof \Throwable) {
            $this->recordRequest($agent, $input, $context, FakeResponseSequence::emptyResponse());
            throw $response;
        }

        $this->recordRequest($agent, $input, $context, $response);

        return $response;
    }

    /**
     * @return Generator<int, StreamEvent>
     */
    public function stream(
        AgentContract $agent,
        string $input,
        ExecutionContext $context,
    ): Generator {
        $response = $this->getResponse($agent->key());

        // Handle throwables
        if ($response instanceof \Throwable) {
            $this->recordRequest($agent, $input, $context, FakeResponseSequence::emptyResponse());
            throw $response;
        }

        $this->recordRequest($agent, $input, $context, $response);

        // Convert PrismResponse to stream events
        yield from $this->createFakeStreamGenerator($response->text);
    }

    /**
     * Get the next response for an agent.
     */
    private function getResponse(string $agentKey): PrismResponse|\Throwable
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
        return FakeResponseSequence::emptyResponse();
    }

    /**
     * Record an agent request.
     */
    private function recordRequest(
        AgentContract $agent,
        string $input,
        ExecutionContext $context,
        PrismResponse $response,
    ): void {
        $this->recordedRequests[] = new RecordedRequest(
            agent: $agent,
            input: $input,
            context: $context,
            response: $response,
            timestamp: time(),
        );
    }

    /**
     * Create a fake stream generator from text.
     *
     * @return Generator<int, StreamEvent>
     */
    private function createFakeStreamGenerator(string $text): Generator
    {
        $timestamp = time();
        $eventId = 0;
        $messageId = 'msg_fake_'.uniqid();

        // Stream start
        yield new StreamStartEvent(
            id: 'evt_'.($eventId++),
            timestamp: $timestamp,
            model: 'fake-model',
            provider: 'fake',
        );

        // Text deltas - split into chunks
        $chunkSize = 10;
        $chunks = str_split($text, $chunkSize);
        foreach ($chunks as $chunk) {
            yield new TextDeltaEvent(
                id: 'evt_'.($eventId++),
                timestamp: $timestamp,
                delta: $chunk,
                messageId: $messageId,
            );
        }

        // Stream end
        yield new StreamEndEvent(
            id: 'evt_'.($eventId++),
            timestamp: $timestamp,
            finishReason: FinishReason::Stop,
            usage: new Usage(
                promptTokens: 10,
                completionTokens: (int) ceil(strlen($text) / 4),
            ),
        );
    }
}
