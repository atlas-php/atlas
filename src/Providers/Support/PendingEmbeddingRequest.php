<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Support;

use Atlasphp\Atlas\Providers\Services\EmbeddingService;

/**
 * Fluent builder for embedding operations.
 *
 * Provides a fluent API for configuring embedding generation with metadata,
 * provider options, and retry support. Uses immutable cloning for method chaining.
 */
final class PendingEmbeddingRequest
{
    use HasMetadataSupport;
    use HasProviderCallbacks;
    use HasProviderSupport;
    use HasRetrySupport;

    public function __construct(
        private readonly EmbeddingService $embeddingService,
    ) {}

    /**
     * Generate embedding(s) for text input.
     *
     * @param  string|array<string>  $input  Single text or array of texts.
     * @return array<int, float>|array<int, array<int, float>> Embedding vector(s).
     */
    public function generate(string|array $input): array
    {
        // Resolve provider and apply any provider-specific callbacks
        $provider = $this->getProviderOverride() ?? config('atlas.embedding.provider');
        $self = $this->applyProviderCallbacks($provider);

        $options = $self->buildOptions();

        if (is_string($input)) {
            return $self->embeddingService->generate($input, $options, $self->getRetryArray());
        }

        return $self->embeddingService->generateBatch($input, $options, $self->getRetryArray());
    }

    /**
     * Get the dimensions of embedding vectors.
     *
     * @return int The number of dimensions.
     */
    public function dimensions(): int
    {
        return $this->embeddingService->dimensions();
    }

    /**
     * Build the options array from fluent configuration.
     *
     * @return array<string, mixed>
     */
    private function buildOptions(): array
    {
        $options = [];

        $provider = $this->getProviderOverride();
        if ($provider !== null) {
            $options['provider'] = $provider;
        }

        $model = $this->getModelOverride();
        if ($model !== null) {
            $options['model'] = $model;
        }

        $providerOptions = $this->getProviderOptions();
        if ($providerOptions !== []) {
            $options['provider_options'] = $providerOptions;
        }

        $metadata = $this->getMetadata();
        if ($metadata !== []) {
            $options['metadata'] = $metadata;
        }

        return $options;
    }
}
