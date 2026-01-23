<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Services;

use Atlasphp\Atlas\Foundation\Services\PipelineRunner;
use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;
use Throwable;

/**
 * Stateless service for image generation operations.
 *
 * Provides image generation using configured providers with pipeline middleware support.
 */
class ImageService
{
    public function __construct(
        private readonly PrismBuilderContract $prismBuilder,
        private readonly ProviderConfigService $configService,
        private readonly PipelineRunner $pipelineRunner,
    ) {}

    /**
     * Generate an image from the given prompt.
     *
     * @param  string  $prompt  The image prompt.
     * @param  array<string, mixed>  $options  Options including provider, model, size, quality, metadata, providerOptions.
     * @param  array{0: array<int, int>|int, 1: \Closure|int, 2: callable|null, 3: bool}|null  $retry  Optional retry configuration.
     * @return array{url: string|null, base64: string|null, revised_prompt: string|null}
     */
    public function generate(string $prompt, array $options = [], ?array $retry = null): array
    {
        $imageConfig = $this->configService->getImageConfig();
        $provider = $options['provider'] ?? $imageConfig['provider'];
        $model = $options['model'] ?? $imageConfig['model'];
        $size = $options['size'] ?? null;
        $quality = $options['quality'] ?? null;
        $metadata = $options['metadata'] ?? [];
        $providerOptions = $options['provider_options'] ?? [];

        try {
            // Run before_generate pipeline
            $beforeData = [
                'prompt' => $prompt,
                'provider' => $provider,
                'model' => $model,
                'options' => $options,
                'size' => $size,
                'quality' => $quality,
                'metadata' => $metadata,
            ];

            /** @var array{prompt: string, provider: string, model: string, options: array<string, mixed>, size: string|null, quality: string|null, metadata: array<string, mixed>} $beforeData */
            $beforeData = $this->pipelineRunner->runIfActive(
                'image.before_generate',
                $beforeData,
            );

            $prompt = $beforeData['prompt'];
            $provider = $beforeData['provider'];
            $model = $beforeData['model'];

            // Build request options
            $requestOptions = array_filter([
                'size' => $beforeData['size'],
                'quality' => $beforeData['quality'],
            ]);

            // Merge with provider-specific options
            $requestOptions = array_merge($requestOptions, $providerOptions);

            // Use explicit retry config or fall back to config-based retry
            $retry = $retry ?? $this->configService->getRetryConfig();

            $request = $this->prismBuilder->forImage($provider, $model, $prompt, $requestOptions, $retry);
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
                'metadata' => $metadata,
                'result' => $result,
            ];

            /** @var array{result: array{url: string|null, base64: string|null, revised_prompt: string|null}} $afterData */
            $afterData = $this->pipelineRunner->runIfActive(
                'image.after_generate',
                $afterData,
            );

            return $afterData['result'];
        } catch (Throwable $e) {
            $this->handleError($prompt, $provider, $model, $size, $quality, $metadata, $e);
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
     * @param  array<string, mixed>  $metadata  The metadata that was provided.
     * @param  Throwable  $exception  The exception that occurred.
     */
    protected function handleError(
        string $prompt,
        string $provider,
        string $model,
        ?string $size,
        ?string $quality,
        array $metadata,
        Throwable $exception,
    ): void {
        $errorData = [
            'prompt' => $prompt,
            'provider' => $provider,
            'model' => $model,
            'size' => $size,
            'quality' => $quality,
            'metadata' => $metadata,
            'exception' => $exception,
        ];

        $this->pipelineRunner->runIfActive('image.on_error', $errorData);
    }
}
