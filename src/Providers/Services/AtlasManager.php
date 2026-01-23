<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Services;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Agents\Support\PendingAgentRequest;
use Atlasphp\Atlas\Providers\Support\PendingEmbeddingRequest;
use Atlasphp\Atlas\Providers\Support\PendingImageRequest;
use Atlasphp\Atlas\Providers\Support\PendingSpeechRequest;

/**
 * Main manager for Atlas capabilities.
 *
 * Provides the primary API for chat, embedding, image, and speech operations.
 * Uses an agent-first pattern for chat operations and fluent builders for
 * configurable operations. All modalities return Pending* wrappers for
 * consistent fluent API.
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
     * schema, retry, and executing the chat operation.
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
     * Start building an embeddings request with configuration.
     *
     * Returns a fluent builder for configuring metadata and retry
     * before generating embeddings.
     */
    public function embeddings(): PendingEmbeddingRequest
    {
        return new PendingEmbeddingRequest($this->embeddingService);
    }

    /**
     * Start building an image generation request with configuration.
     *
     * Returns a fluent builder for configuring provider, model, size,
     * quality, metadata, and retry before generating images.
     *
     * @param  string|null  $provider  Optional provider name to use.
     * @param  string|null  $model  Optional model name to use.
     */
    public function image(?string $provider = null, ?string $model = null): PendingImageRequest
    {
        $request = new PendingImageRequest($this->imageService);

        if ($provider !== null) {
            $request = $request->withProvider($provider, $model);
        } elseif ($model !== null) {
            $request = $request->withModel($model);
        }

        return $request;
    }

    /**
     * Start building a speech request with configuration.
     *
     * Returns a fluent builder for configuring provider, model, voice,
     * format, speed, metadata, and retry before speech operations.
     *
     * @param  string|null  $provider  Optional provider name to use.
     * @param  string|null  $model  Optional model name to use.
     */
    public function speech(?string $provider = null, ?string $model = null): PendingSpeechRequest
    {
        $request = new PendingSpeechRequest($this->speechService);

        if ($provider !== null) {
            $request = $request->withProvider($provider, $model);
        } elseif ($model !== null) {
            $request = $request->withModel($model);
        }

        return $request;
    }
}
