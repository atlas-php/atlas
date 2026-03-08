<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Support\AgentContext;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\AgentStreamResponse;
use Atlasphp\Atlas\Testing\Support\FakeResponseSequence;
use Atlasphp\Atlas\Testing\Support\RecordedRequest;
use Closure;
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
     * Closure-based response factories keyed by agent key.
     *
     * @var array<string, Closure(RecordedRequest): (PrismResponse|string)>
     */
    private array $responseFactories = [];

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
     * Register a closure-based response factory for an agent.
     *
     * The closure receives a RecordedRequest and should return a PrismResponse or string.
     *
     * @param  string  $agentKey  The agent key.
     * @param  Closure(RecordedRequest): (PrismResponse|string)  $factory  The response factory.
     */
    public function respondUsing(string $agentKey, Closure $factory): self
    {
        $this->responseFactories[$agentKey] = $factory;

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
        AgentContext $context,
    ): AgentResponse {
        // Check for closure-based response factory first
        if (isset($this->responseFactories[$agent->key()])) {
            return $this->executeWithFactory($agent, $input, $context);
        }

        $prismResponse = $this->getResponse($agent->key());

        // Handle throwables
        if ($prismResponse instanceof \Throwable) {
            $emptyAgentResponse = new AgentResponse(
                response: FakeResponseSequence::emptyResponse(),
                agent: $agent,
                input: $input,
                systemPrompt: null,
                context: $context,
            );
            $this->recordRequest($agent, $input, $context, $emptyAgentResponse, wasStreamed: false);
            throw $prismResponse;
        }

        $agentResponse = new AgentResponse(
            response: $prismResponse,
            agent: $agent,
            input: $input,
            systemPrompt: null,
            context: $context,
        );

        $this->recordRequest($agent, $input, $context, $agentResponse, wasStreamed: false);

        return $agentResponse;
    }

    public function stream(
        AgentContract $agent,
        string $input,
        AgentContext $context,
    ): AgentStreamResponse {
        // Check for closure-based response factory first
        if (isset($this->responseFactories[$agent->key()])) {
            return $this->streamWithFactory($agent, $input, $context);
        }

        $prismResponse = $this->getResponse($agent->key());

        // Handle throwables
        if ($prismResponse instanceof \Throwable) {
            $emptyResponse = new AgentResponse(
                response: FakeResponseSequence::emptyResponse(),
                agent: $agent,
                input: $input,
                systemPrompt: null,
                context: $context,
            );
            $this->recordRequest($agent, $input, $context, $emptyResponse, wasStreamed: true);
            throw $prismResponse;
        }

        $agentResponse = new AgentResponse(
            response: $prismResponse,
            agent: $agent,
            input: $input,
            systemPrompt: null,
            context: $context,
        );

        $this->recordRequest($agent, $input, $context, $agentResponse, wasStreamed: true);

        // Convert PrismResponse to stream events wrapped in AgentStreamResponse
        return new AgentStreamResponse(
            stream: $this->createFakeStreamGenerator($prismResponse->text),
            agent: $agent,
            input: $input,
            systemPrompt: null,
            context: $context,
        );
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
     * Execute using a closure-based response factory.
     */
    private function executeWithFactory(
        AgentContract $agent,
        string $input,
        AgentContext $context,
    ): AgentResponse {
        $tempRequest = new RecordedRequest(
            agent: $agent,
            input: $input,
            context: $context,
            response: new AgentResponse(
                response: FakeResponseSequence::emptyResponse(),
                agent: $agent,
                input: $input,
                systemPrompt: null,
                context: $context,
            ),
            timestamp: time(),
            wasStreamed: false,
        );

        $result = ($this->responseFactories[$agent->key()])($tempRequest);
        $prismResponse = $this->resolveFactoryResult($result);

        $agentResponse = new AgentResponse(
            response: $prismResponse,
            agent: $agent,
            input: $input,
            systemPrompt: null,
            context: $context,
        );

        $this->recordRequest($agent, $input, $context, $agentResponse, wasStreamed: false);

        return $agentResponse;
    }

    /**
     * Stream using a closure-based response factory.
     */
    private function streamWithFactory(
        AgentContract $agent,
        string $input,
        AgentContext $context,
    ): AgentStreamResponse {
        $tempRequest = new RecordedRequest(
            agent: $agent,
            input: $input,
            context: $context,
            response: new AgentResponse(
                response: FakeResponseSequence::emptyResponse(),
                agent: $agent,
                input: $input,
                systemPrompt: null,
                context: $context,
            ),
            timestamp: time(),
            wasStreamed: true,
        );

        $result = ($this->responseFactories[$agent->key()])($tempRequest);
        $prismResponse = $this->resolveFactoryResult($result);

        $agentResponse = new AgentResponse(
            response: $prismResponse,
            agent: $agent,
            input: $input,
            systemPrompt: null,
            context: $context,
        );

        $this->recordRequest($agent, $input, $context, $agentResponse, wasStreamed: true);

        return new AgentStreamResponse(
            stream: $this->createFakeStreamGenerator($prismResponse->text),
            agent: $agent,
            input: $input,
            systemPrompt: null,
            context: $context,
        );
    }

    /**
     * Resolve a factory result to a PrismResponse.
     */
    private function resolveFactoryResult(mixed $result): PrismResponse
    {
        if ($result instanceof PrismResponse) {
            return $result;
        }

        if (is_string($result)) {
            $empty = FakeResponseSequence::emptyResponse();

            return new PrismResponse(
                steps: $empty->steps,
                text: $result,
                finishReason: $empty->finishReason,
                toolCalls: $empty->toolCalls,
                toolResults: $empty->toolResults,
                usage: $empty->usage,
                meta: $empty->meta,
                messages: $empty->messages,
            );
        }

        throw new RuntimeException('respondUsing factory must return a PrismResponse or string.');
    }

    /**
     * Record an agent request.
     */
    private function recordRequest(
        AgentContract $agent,
        string $input,
        AgentContext $context,
        AgentResponse $response,
        bool $wasStreamed = false,
    ): void {
        $this->recordedRequests[] = new RecordedRequest(
            agent: $agent,
            input: $input,
            context: $context,
            response: $response,
            timestamp: time(),
            wasStreamed: $wasStreamed,
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
