<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Services;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Providers\Support\MessageContextBuilder;
use Atlasphp\Atlas\Providers\Support\PendingAtlasRequest;
use Atlasphp\Atlas\Streaming\StreamResponse;
use Closure;
use Prism\Prism\Contracts\Schema;

/**
 * Main manager for Atlas capabilities.
 *
 * Provides the primary API for chat, embedding, image, and speech operations.
 * Orchestrates agent execution, embeddings, and other AI capabilities.
 */
class AtlasManager
{
    public function __construct(
        protected AgentResolver $agentResolver,
        protected AgentExecutorContract $agentExecutor,
        protected EmbeddingService $embeddingService,
        protected ImageService $imageService,
        protected SpeechService $speechService,
    ) {}

    /**
     * Configure retry behavior for subsequent API requests.
     *
     * Returns a fluent builder that captures the retry configuration
     * and passes it through to Prism's withClientRetry() method.
     *
     * @param  array<int, int>|int  $times  Number of attempts OR array of delays [100, 200, 300].
     * @param  Closure|int  $sleepMilliseconds  Fixed ms OR fn(int $attempt, Throwable $e): int for dynamic.
     * @param  callable|null  $when  fn(Throwable $e, PendingRequest $req): bool to control retry conditions.
     * @param  bool  $throw  Whether to throw after all retries fail.
     */
    public function withRetry(
        array|int $times,
        Closure|int $sleepMilliseconds = 0,
        ?callable $when = null,
        bool $throw = true,
    ): PendingAtlasRequest {
        return (new PendingAtlasRequest($this))
            ->withRetry($times, $sleepMilliseconds, $when, $throw);
    }

    /**
     * Execute a chat with an agent.
     *
     * When stream is false (default), returns an AgentResponse with the complete response.
     * When stream is true, returns a StreamResponse that can be iterated for real-time events.
     *
     * @param  string|AgentContract  $agent  The agent key, class, or instance.
     * @param  string  $input  The user input message.
     * @param  array<int, array{role: string, content: string}>|null  $messages  Optional conversation history.
     * @param  Schema|null  $schema  Optional schema for structured output (not supported with streaming).
     * @param  bool  $stream  Whether to stream the response.
     * @param  array{0: array<int, int>|int, 1: Closure|int, 2: callable|null, 3: bool}|null  $retry  Optional retry configuration.
     */
    public function chat(
        string|AgentContract $agent,
        string $input,
        ?array $messages = null,
        ?Schema $schema = null,
        bool $stream = false,
        ?array $retry = null,
    ): AgentResponse|StreamResponse {
        $resolvedAgent = $this->agentResolver->resolve($agent);

        $context = $messages !== null
            ? new ExecutionContext(messages: $messages)
            : null;

        if ($stream) {
            if ($schema !== null) {
                throw new \InvalidArgumentException(
                    'Streaming does not support structured output (schema). Use stream: false for structured responses.'
                );
            }

            return $this->agentExecutor->stream($resolvedAgent, $input, $context, $retry);
        }

        return $this->agentExecutor->execute($resolvedAgent, $input, $context, $schema, $retry);
    }

    /**
     * Create a message context builder for multi-turn conversations.
     *
     * @param  array<int, array{role: string, content: string}>  $messages  The conversation history.
     */
    public function forMessages(array $messages): MessageContextBuilder
    {
        return new MessageContextBuilder($this, $messages);
    }

    /**
     * Execute an agent with a full execution context.
     *
     * Used internally by MessageContextBuilder to execute with variables and metadata.
     *
     * @param  string|AgentContract  $agent  The agent key, class, or instance.
     * @param  string  $input  The user input message.
     * @param  ExecutionContext  $context  The execution context with messages, variables, and metadata.
     * @param  Schema|null  $schema  Optional schema for structured output.
     * @param  array{0: array<int, int>|int, 1: Closure|int, 2: callable|null, 3: bool}|null  $retry  Optional retry configuration.
     */
    public function executeWithContext(
        string|AgentContract $agent,
        string $input,
        ExecutionContext $context,
        ?Schema $schema = null,
        ?array $retry = null,
    ): AgentResponse {
        $resolvedAgent = $this->agentResolver->resolve($agent);

        return $this->agentExecutor->execute($resolvedAgent, $input, $context, $schema, $retry);
    }

    /**
     * Stream a response from an agent with a full execution context.
     *
     * Used internally by MessageContextBuilder to stream with variables and metadata.
     *
     * @param  string|AgentContract  $agent  The agent key, class, or instance.
     * @param  string  $input  The user input message.
     * @param  ExecutionContext  $context  The execution context with messages, variables, and metadata.
     * @param  array{0: array<int, int>|int, 1: Closure|int, 2: callable|null, 3: bool}|null  $retry  Optional retry configuration.
     */
    public function streamWithContext(
        string|AgentContract $agent,
        string $input,
        ExecutionContext $context,
        ?array $retry = null,
    ): StreamResponse {
        $resolvedAgent = $this->agentResolver->resolve($agent);

        return $this->agentExecutor->stream($resolvedAgent, $input, $context, $retry);
    }

    /**
     * Generate an embedding for a single text input.
     *
     * @param  string  $text  The text to embed.
     * @param  array{0: array<int, int>|int, 1: Closure|int, 2: callable|null, 3: bool}|null  $retry  Optional retry configuration.
     * @return array<int, float> The embedding vector.
     */
    public function embed(string $text, ?array $retry = null): array
    {
        return $this->embeddingService->generate($text, [], $retry);
    }

    /**
     * Generate embeddings for multiple text inputs.
     *
     * @param  array<string>  $texts  The texts to embed.
     * @param  array{0: array<int, int>|int, 1: Closure|int, 2: callable|null, 3: bool}|null  $retry  Optional retry configuration.
     * @return array<int, array<int, float>> Array of embedding vectors.
     */
    public function embedBatch(array $texts, ?array $retry = null): array
    {
        return $this->embeddingService->generateBatch($texts, [], $retry);
    }

    /**
     * Get the dimensions of embedding vectors.
     *
     * @return int The number of dimensions.
     */
    public function embeddingDimensions(): int
    {
        return $this->embeddingService->dimensions();
    }

    /**
     * Get the image service for fluent configuration.
     *
     * @param  string|null  $provider  Optional provider name to use.
     * @param  string|null  $model  Optional model name to use.
     * @return ImageService The image service instance.
     */
    public function image(?string $provider = null, ?string $model = null): ImageService
    {
        $service = $this->imageService;

        if ($provider !== null) {
            $service = $service->using($provider);
        }

        if ($model !== null) {
            $service = $service->model($model);
        }

        return $service;
    }

    /**
     * Get the speech service for fluent configuration.
     *
     * @param  string|null  $provider  Optional provider name to use.
     * @param  string|null  $model  Optional model name to use.
     * @return SpeechService The speech service instance.
     */
    public function speech(?string $provider = null, ?string $model = null): SpeechService
    {
        $service = $this->speechService;

        if ($provider !== null) {
            $service = $service->using($provider);
        }

        if ($model !== null) {
            $service = $service->model($model);
        }

        return $service;
    }
}
