<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Services;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Agents\Support\PendingAgentRequest;
use Atlasphp\Atlas\Providers\Support\PendingEmbeddingRequest;

/**
 * Main manager for Atlas capabilities.
 *
 * Provides the primary API for chat, embedding, image, and speech operations.
 * Uses an agent-first pattern for chat operations and fluent builders for
 * configurable operations.
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
     * Start building a chat request for the given agent.
     *
     * Returns a fluent builder for configuring messages, variables, metadata,
     * retry, and executing the chat operation.
     *
     * @param  string|AgentContract  $agent  The agent key, class, or instance.
     */
    public function agent(string|AgentContract $agent): PendingAgentRequest
    {
        return new PendingAgentRequest(
            $this->agentResolver,
            $this->agentExecutor,
            $agent,
        );
    }

    /**
     * Start building an embedding request with configuration.
     *
     * Returns a fluent builder for configuring metadata and retry
     * before generating embeddings.
     */
    public function embedding(): PendingEmbeddingRequest
    {
        return new PendingEmbeddingRequest($this->embeddingService);
    }

    /**
     * Generate an embedding for a single text input.
     *
     * Simple shortcut when no configuration is needed.
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
     * Simple shortcut when no configuration is needed.
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
