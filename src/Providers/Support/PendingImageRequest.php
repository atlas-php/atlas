<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Support;

use Atlasphp\Atlas\Providers\Services\ImageService;

/**
 * Fluent builder for image generation operations.
 *
 * Provides a fluent API for configuring image generation with metadata
 * and retry support. Uses immutable cloning for method chaining.
 */
final class PendingImageRequest
{
    use HasMetadataSupport;
    use HasProviderCallbacks;
    use HasProviderSupport;
    use HasRetrySupport;

    private ?string $size = null;

    private ?string $quality = null;

    public function __construct(
        private readonly ImageService $imageService,
    ) {}

    /**
     * Set the image size.
     */
    public function withSize(string $size): static
    {
        $clone = clone $this;
        $clone->size = $size;

        return $clone;
    }

    /**
     * Set the image quality.
     */
    public function withQuality(string $quality): static
    {
        $clone = clone $this;
        $clone->quality = $quality;

        return $clone;
    }

    /**
     * Generate an image from the given prompt.
     *
     * @param  string  $prompt  The image prompt.
     * @param  array<string, mixed>  $options  Additional options.
     * @return array{url: string|null, base64: string|null, revised_prompt: string|null}
     */
    public function generate(string $prompt, array $options = []): array
    {
        // Resolve provider and apply any provider-specific callbacks
        $provider = $this->getProviderOverride() ?? config('atlas.image.provider');
        $self = $this->applyProviderCallbacks($provider);

        return $self->imageService->generate($prompt, $self->buildOptions($options), $self->getRetryArray());
    }

    /**
     * Build the options array from fluent configuration.
     *
     * @param  array<string, mixed>  $additionalOptions
     * @return array<string, mixed>
     */
    private function buildOptions(array $additionalOptions = []): array
    {
        $options = $additionalOptions;

        $provider = $this->getProviderOverride();
        if ($provider !== null) {
            $options['provider'] = $provider;
        }

        $model = $this->getModelOverride();
        if ($model !== null) {
            $options['model'] = $model;
        }

        if ($this->size !== null) {
            $options['size'] = $this->size;
        }

        if ($this->quality !== null) {
            $options['quality'] = $this->quality;
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
