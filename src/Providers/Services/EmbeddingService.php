<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Services;

use Atlasphp\Atlas\Foundation\Services\PipelineRunner;
use Atlasphp\Atlas\Providers\Contracts\EmbeddingProviderContract;
use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;
use Atlasphp\Atlas\Providers\Support\ConvertsProviderExceptions;
use Throwable;

/**
 * Stateless service layer for generating text embeddings.
 *
 * Delegates to the configured embedding provider while providing a clean API
 * with pipeline middleware support for observability.
 */
class EmbeddingService
{
    use ConvertsProviderExceptions;

    public function __construct(
        private readonly EmbeddingProviderContract $provider,
        private readonly PipelineRunner $pipelineRunner,
        private readonly ProviderConfigService $configService,
        private readonly PrismBuilderContract $prismBuilder,
    ) {}

    /**
     * Generate an embedding for a single text input.
     *
     * @param  string  $text  The text to embed.
     * @param  array<string, mixed>  $options  Options including provider, model, metadata, provider_options.
     * @param  array{0: array<int, int>|int, 1: \Closure|int, 2: callable|null, 3: bool}|null  $retry  Optional retry configuration.
     * @return array<int, float>
     */
    public function generate(string $text, array $options = [], ?array $retry = null): array
    {
        $providerOverride = $options['provider'] ?? null;
        $modelOverride = $options['model'] ?? null;
        $provider = $providerOverride ?? $this->provider->provider();
        $model = $modelOverride ?? $this->provider->model();
        $metadata = $options['metadata'] ?? [];
        $providerOptions = $options['provider_options'] ?? [];

        try {
            // Run before_generate pipeline
            $beforeData = [
                'text' => $text,
                'provider' => $provider,
                'model' => $model,
                'options' => $options,
                'metadata' => $metadata,
            ];

            /** @var array{text: string, provider: string, model: string, options: array<string, mixed>, metadata: array<string, mixed>} $beforeData */
            $beforeData = $this->pipelineRunner->runIfActive(
                'embedding.before_generate',
                $beforeData,
            );

            $text = $beforeData['text'];
            $options = $beforeData['options'];

            // Use explicit retry config or fall back to config-based retry
            $retry = $retry ?? $this->configService->getRetryConfig();

            // Merge provider options into request options
            $requestOptions = array_merge($options, $providerOptions);

            // Generate embedding - use PrismBuilder directly if overrides are set
            if ($providerOverride !== null || $modelOverride !== null) {
                $request = $this->prismBuilder->forEmbeddings($provider, $model, $text, $requestOptions, $retry);
                $response = $request->asEmbeddings();
                $result = isset($response->embeddings[0]) ? $response->embeddings[0]->embedding : [];
            } else {
                $result = $this->provider->generate($text, $requestOptions, $retry);
            }

            // Run after_generate pipeline
            $afterData = [
                'text' => $text,
                'provider' => $provider,
                'model' => $model,
                'options' => $options,
                'metadata' => $metadata,
                'result' => $result,
            ];

            /** @var array{result: array<int, float>} $afterData */
            $afterData = $this->pipelineRunner->runIfActive(
                'embedding.after_generate',
                $afterData,
            );

            return $afterData['result'];
        } catch (Throwable $e) {
            $converted = $this->convertPrismException($e);
            $this->handleError('generate', $text, null, $provider, $model, $metadata, $converted);
            throw $converted;
        }
    }

    /**
     * Generate embeddings for multiple text inputs.
     *
     * @param  array<string>  $texts  The texts to embed.
     * @param  array<string, mixed>  $options  Options including provider, model, metadata, provider_options.
     * @param  array{0: array<int, int>|int, 1: \Closure|int, 2: callable|null, 3: bool}|null  $retry  Optional retry configuration.
     * @return array<int, array<int, float>>
     */
    public function generateBatch(array $texts, array $options = [], ?array $retry = null): array
    {
        $embeddingConfig = $this->configService->getEmbeddingConfig();
        $providerOverride = $options['provider'] ?? null;
        $modelOverride = $options['model'] ?? null;
        $provider = $providerOverride ?? $this->provider->provider();
        $model = $modelOverride ?? $this->provider->model();
        $metadata = $options['metadata'] ?? [];
        $providerOptions = $options['provider_options'] ?? [];

        try {
            // Run before_generate_batch pipeline
            $beforeData = [
                'texts' => $texts,
                'provider' => $provider,
                'model' => $model,
                'options' => $options,
                'metadata' => $metadata,
            ];

            /** @var array{texts: array<string>, provider: string, model: string, options: array<string, mixed>, metadata: array<string, mixed>} $beforeData */
            $beforeData = $this->pipelineRunner->runIfActive(
                'embedding.before_generate_batch',
                $beforeData,
            );

            $texts = $beforeData['texts'];
            $options = $beforeData['options'];

            // Use explicit retry config or fall back to config-based retry
            $retry = $retry ?? $this->configService->getRetryConfig();

            // Merge provider options into request options
            $requestOptions = array_merge($options, $providerOptions);

            // Generate embeddings - use PrismBuilder directly if overrides are set
            if ($providerOverride !== null || $modelOverride !== null) {
                $batchSize = $embeddingConfig['batch_size'];
                $result = [];
                $batches = array_chunk($texts, $batchSize);

                foreach ($batches as $batch) {
                    $request = $this->prismBuilder->forEmbeddings($provider, $model, $batch, $requestOptions, $retry);
                    $response = $request->asEmbeddings();

                    foreach ($response->embeddings as $embedding) {
                        $result[] = $embedding->embedding;
                    }
                }
            } else {
                $result = $this->provider->generateBatch($texts, $requestOptions, $retry);
            }

            // Run after_generate_batch pipeline
            $afterData = [
                'texts' => $texts,
                'provider' => $provider,
                'model' => $model,
                'options' => $options,
                'metadata' => $metadata,
                'result' => $result,
            ];

            /** @var array{result: array<int, array<int, float>>} $afterData */
            $afterData = $this->pipelineRunner->runIfActive(
                'embedding.after_generate_batch',
                $afterData,
            );

            return $afterData['result'];
        } catch (Throwable $e) {
            $converted = $this->convertPrismException($e);
            $this->handleError('generate_batch', null, $texts, $provider, $model, $metadata, $converted);
            throw $converted;
        }
    }

    /**
     * Get the dimensions of the embedding vectors.
     */
    public function dimensions(): int
    {
        return $this->provider->dimensions();
    }

    /**
     * Handle an error by running the error pipeline.
     *
     * @param  string  $operation  The operation that failed ('generate' or 'generate_batch').
     * @param  string|null  $text  The single text (if generating single).
     * @param  array<string>|null  $texts  The batch texts (if generating batch).
     * @param  string  $provider  The provider being used.
     * @param  string  $model  The model being used.
     * @param  array<string, mixed>  $metadata  The metadata that was provided.
     * @param  Throwable  $exception  The exception that occurred.
     */
    protected function handleError(
        string $operation,
        ?string $text,
        ?array $texts,
        string $provider,
        string $model,
        array $metadata,
        Throwable $exception,
    ): void {
        $errorData = [
            'operation' => $operation,
            'text' => $text,
            'texts' => $texts,
            'provider' => $provider,
            'model' => $model,
            'metadata' => $metadata,
            'exception' => $exception,
        ];

        $this->pipelineRunner->runIfActive('embedding.on_error', $errorData);
    }
}
