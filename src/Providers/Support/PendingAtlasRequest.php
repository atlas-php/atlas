<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Support;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Providers\Services\AtlasManager;
use Atlasphp\Atlas\Providers\Services\ImageService;
use Atlasphp\Atlas\Providers\Services\SpeechService;
use Atlasphp\Atlas\Streaming\StreamResponse;
use Prism\Prism\Contracts\Schema;

/**
 * Fluent builder for Atlas requests with retry configuration.
 *
 * Captures retry configuration and forwards method calls to AtlasManager
 * with the retry config applied. Uses immutable cloning for method chaining.
 */
final class PendingAtlasRequest
{
    use HasRetrySupport;

    public function __construct(
        private readonly AtlasManager $manager,
    ) {}

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
     */
    public function chat(
        string|AgentContract $agent,
        string $input,
        ?array $messages = null,
        ?Schema $schema = null,
        bool $stream = false,
    ): AgentResponse|StreamResponse {
        return $this->manager->chat($agent, $input, $messages, $schema, $stream, $this->getRetryArray());
    }

    /**
     * Create a message context builder for multi-turn conversations.
     *
     * @param  array<int, array{role: string, content: string}>  $messages  The conversation history.
     */
    public function forMessages(array $messages): MessageContextBuilder
    {
        return new MessageContextBuilder($this->manager, $messages, [], [], $this->getRetryArray());
    }

    /**
     * Generate an embedding for a single text input.
     *
     * @param  string  $text  The text to embed.
     * @return array<int, float> The embedding vector.
     */
    public function embed(string $text): array
    {
        return $this->manager->embed($text, $this->getRetryArray());
    }

    /**
     * Generate embeddings for multiple text inputs.
     *
     * @param  array<string>  $texts  The texts to embed.
     * @return array<int, array<int, float>> Array of embedding vectors.
     */
    public function embedBatch(array $texts): array
    {
        return $this->manager->embedBatch($texts, $this->getRetryArray());
    }

    /**
     * Get the image service for fluent configuration.
     *
     * @param  string|null  $provider  Optional provider name to use.
     * @param  string|null  $model  Optional model name to use.
     */
    public function image(?string $provider = null, ?string $model = null): ImageService
    {
        $service = $this->manager->image($provider, $model);

        $retry = $this->getRetryArray();
        if ($retry !== null) {
            $service = $service->withRetry(...$retry);
        }

        return $service;
    }

    /**
     * Get the speech service for fluent configuration.
     *
     * @param  string|null  $provider  Optional provider name to use.
     * @param  string|null  $model  Optional model name to use.
     */
    public function speech(?string $provider = null, ?string $model = null): SpeechService
    {
        $service = $this->manager->speech($provider, $model);

        $retry = $this->getRetryArray();
        if ($retry !== null) {
            $service = $service->withRetry(...$retry);
        }

        return $service;
    }
}
