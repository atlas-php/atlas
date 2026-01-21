<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Services;

/**
 * Main manager for Atlas capabilities.
 *
 * Provides the primary API for embedding, image, and speech operations.
 * Chat methods will be added in Phase 3.
 */
class AtlasManager
{
    public function __construct(
        protected EmbeddingService $embeddingService,
        protected ImageService $imageService,
        protected SpeechService $speechService,
    ) {}

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
     * @return ImageService The image service instance.
     */
    public function image(): ImageService
    {
        return $this->imageService;
    }

    /**
     * Get the speech service for fluent configuration.
     *
     * @return SpeechService The speech service instance.
     */
    public function speech(): SpeechService
    {
        return $this->speechService;
    }
}
