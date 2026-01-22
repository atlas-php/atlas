<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Services;

use Atlasphp\Atlas\Foundation\Services\PipelineRunner;
use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;
use Throwable;

/**
 * Service for image generation operations.
 *
 * Provides a fluent API for generating images using configured providers.
 * Uses clone pattern for immutability with pipeline middleware support.
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
        private readonly PipelineRunner $pipelineRunner,
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

        try {
            // Run before_generate pipeline
            $beforeData = [
                'prompt' => $prompt,
                'provider' => $provider,
                'model' => $model,
                'options' => $options,
                'size' => $this->size ?? $options['size'] ?? null,
                'quality' => $this->quality ?? $options['quality'] ?? null,
            ];

            /** @var array{prompt: string, provider: string, model: string, options: array<string, mixed>, size: string|null, quality: string|null} $beforeData */
            $beforeData = $this->pipelineRunner->runIfActive(
                'image.before_generate',
                $beforeData,
            );

            $prompt = $beforeData['prompt'];
            $provider = $beforeData['provider'];
            $model = $beforeData['model'];

            $requestOptions = array_filter([
                'size' => $beforeData['size'],
                'quality' => $beforeData['quality'],
            ]);

            $request = $this->prismBuilder->forImage($provider, $model, $prompt, $requestOptions);
            $response = $request->generate();

            // Prism returns images in an array - safely access the property
            $images = $response->images ?? [];
            $image = $images[0] ?? null;

            $result = [
                'url' => $image?->url,
                'base64' => $image?->base64,
                'revised_prompt' => $image?->revisedPrompt,
            ];

            // Run after_generate pipeline
            $afterData = [
                'prompt' => $prompt,
                'provider' => $provider,
                'model' => $model,
                'size' => $beforeData['size'],
                'quality' => $beforeData['quality'],
                'result' => $result,
            ];

            /** @var array{result: array{url: string|null, base64: string|null, revised_prompt: string|null}} $afterData */
            $afterData = $this->pipelineRunner->runIfActive(
                'image.after_generate',
                $afterData,
            );

            return $afterData['result'];
        } catch (Throwable $e) {
            $this->handleError(
                $prompt,
                $provider,
                $model,
                $this->size ?? $options['size'] ?? null,
                $this->quality ?? $options['quality'] ?? null,
                $e,
            );
            throw $e;
        }
    }

    /**
     * Handle an error by running the error pipeline.
     *
     * @param  string  $prompt  The prompt that was used.
     * @param  string  $provider  The provider that was used.
     * @param  string  $model  The model that was used.
     * @param  string|null  $size  The size that was requested.
     * @param  string|null  $quality  The quality that was requested.
     * @param  Throwable  $exception  The exception that occurred.
     */
    protected function handleError(
        string $prompt,
        string $provider,
        string $model,
        ?string $size,
        ?string $quality,
        Throwable $exception,
    ): void {
        $errorData = [
            'prompt' => $prompt,
            'provider' => $provider,
            'model' => $model,
            'size' => $size,
            'quality' => $quality,
            'exception' => $exception,
        ];

        $this->pipelineRunner->runIfActive('image.on_error', $errorData);
    }
}
