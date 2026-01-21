<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Services;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Providers\Support\MessageContextBuilder;
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
     * Execute a chat with an agent.
     *
     * @param  string|AgentContract  $agent  The agent key, class, or instance.
     * @param  string  $input  The user input message.
     * @param  array<int, array{role: string, content: string}>|null  $messages  Optional conversation history.
     * @param  Schema|null  $schema  Optional schema for structured output.
     */
    public function chat(
        string|AgentContract $agent,
        string $input,
        ?array $messages = null,
        ?Schema $schema = null,
    ): AgentResponse {
        $resolvedAgent = $this->agentResolver->resolve($agent);

        $context = $messages !== null
            ? new ExecutionContext(messages: $messages)
            : null;

        return $this->agentExecutor->execute($resolvedAgent, $input, $context, $schema);
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
     */
    public function executeWithContext(
        string|AgentContract $agent,
        string $input,
        ExecutionContext $context,
        ?Schema $schema = null,
    ): AgentResponse {
        $resolvedAgent = $this->agentResolver->resolve($agent);

        return $this->agentExecutor->execute($resolvedAgent, $input, $context, $schema);
    }

    /**
     * Generate an embedding for a single text input.
     *
     * @param  string  $text  The text to embed.
     * @return array<int, float> The embedding vector.
     */
    public function embed(string $text): array
    {
        return $this->embeddingService->generate($text);
    }

    /**
     * Generate embeddings for multiple text inputs.
     *
     * @param  array<string>  $texts  The texts to embed.
     * @return array<int, array<int, float>> Array of embedding vectors.
     */
    public function embedBatch(array $texts): array
    {
        return $this->embeddingService->generateBatch($texts);
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
