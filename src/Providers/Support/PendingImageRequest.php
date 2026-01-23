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
    use HasRetrySupport;

    private ?string $provider = null;

    private ?string $model = null;

    private ?string $size = null;

    private ?string $quality = null;

    /**
     * Provider-specific options to pass through to Prism.
     *
     * @var array<string, mixed>
     */
    private array $providerOptions = [];

    public function __construct(
        private readonly ImageService $imageService,
    ) {}

    /**
     * Set the provider for image generation.
     */
    public function using(string $provider): static
    {
        $clone = clone $this;
        $clone->provider = $provider;

        return $clone;
    }

    /**
     * Set the model for image generation.
     */
    public function model(string $model): static
    {
        $clone = clone $this;
        $clone->model = $model;

        return $clone;
    }

    /**
     * Set the image size.
     */
    public function size(string $size): static
    {
        $clone = clone $this;
        $clone->size = $size;

        return $clone;
    }

    /**
     * Set the image quality.
     */
    public function quality(string $quality): static
    {
        $clone = clone $this;
        $clone->quality = $quality;

        return $clone;
    }

    /**
     * Set provider-specific options.
     *
     * These options are passed directly to the provider via Prism's withProviderOptions().
     * Use this for provider-specific features like style, response_format, etc.
     *
     * @param  array<string, mixed>  $options  Provider-specific options.
     */
    public function withProviderOptions(array $options): static
    {
        $clone = clone $this;
        $clone->providerOptions = array_merge($clone->providerOptions, $options);

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
        $service = $this->buildConfiguredService();

        return $service->generate($prompt, $options);
    }

    /**
     * Build a configured ImageService instance with all fluent settings applied.
     */
    private function buildConfiguredService(): ImageService
    {
        $service = $this->imageService;

        if ($this->provider !== null) {
            $service = $service->using($this->provider);
        }

        if ($this->model !== null) {
            $service = $service->model($this->model);
        }

        if ($this->size !== null) {
            $service = $service->size($this->size);
        }

        if ($this->quality !== null) {
            $service = $service->quality($this->quality);
        }

        if ($this->providerOptions !== []) {
            $service = $service->withProviderOptions($this->providerOptions);
        }

        if ($this->getMetadata() !== []) {
            $service = $service->withMetadata($this->getMetadata());
        }

        $retryConfig = $this->getRetryArray();
        if ($retryConfig !== null) {
            $service = $service->withRetry(...$retryConfig);
        }

        return $service;
    }
}
