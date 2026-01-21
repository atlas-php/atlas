<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Services;

use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;

/**
 * Service for image generation operations.
 *
 * Provides a fluent API for generating images using configured providers.
 * Uses clone pattern for immutability.
 */
class ImageService
{
    private ?string $provider = null;

    private ?string $model = null;

    private ?string $size = null;

    private ?string $quality = null;

    public function __construct(
        private readonly PrismBuilderContract $prismBuilder,
        private readonly ProviderConfigService $configService,
    ) {}

    /**
     * Set the provider for image generation.
     */
    public function using(string $provider): self
    {
        $clone = clone $this;
        $clone->provider = $provider;

        return $clone;
    }

    /**
     * Set the model for image generation.
     */
    public function model(string $model): self
    {
        $clone = clone $this;
        $clone->model = $model;

        return $clone;
    }

    /**
     * Set the image size.
     */
    public function size(string $size): self
    {
        $clone = clone $this;
        $clone->size = $size;

        return $clone;
    }

    /**
     * Set the image quality.
     */
    public function quality(string $quality): self
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
        $imageConfig = $this->configService->getImageConfig();
        $provider = $this->provider ?? $options['provider'] ?? $imageConfig['provider'];
        $model = $this->model ?? $options['model'] ?? $imageConfig['model'];

        $requestOptions = array_filter([
            'size' => $this->size ?? $options['size'] ?? null,
            'quality' => $this->quality ?? $options['quality'] ?? null,
        ]);

        $request = $this->prismBuilder->forImage($provider, $model, $prompt, $requestOptions);
        $response = $request->generate();

        // Prism returns images in an array
        $image = $response->images[0] ?? null;

        return [
            'url' => $image?->url,
            'base64' => $image?->base64,
            'revised_prompt' => $image?->revisedPrompt,
        ];
    }
}
