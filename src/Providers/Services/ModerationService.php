<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Services;

use Atlasphp\Atlas\Foundation\Services\PipelineRunner;
use Atlasphp\Atlas\Providers\Contracts\PrismBuilderContract;
use Atlasphp\Atlas\Providers\Support\ConvertsProviderExceptions;
use Atlasphp\Atlas\Providers\Support\ModerationResponse;
use Throwable;

/**
 * Stateless service for content moderation operations.
 *
 * Provides content moderation using configured providers with pipeline middleware support.
 * Currently only OpenAI supports moderation operations.
 */
class ModerationService
{
    use ConvertsProviderExceptions;

    public function __construct(
        private readonly PrismBuilderContract $prismBuilder,
        private readonly ProviderConfigService $configService,
        private readonly PipelineRunner $pipelineRunner,
    ) {}

    /**
     * Moderate the given input text(s).
     *
     * @param  string|array<string>  $input  Single text or array of texts to moderate.
     * @param  array<string, mixed>  $options  Options including provider, model, metadata, providerOptions.
     * @param  array{0: array<int, int>|int, 1: \Closure|int, 2: callable|null, 3: bool}|null  $retry  Optional retry configuration.
     */
    public function moderate(string|array $input, array $options = [], ?array $retry = null): ModerationResponse
    {
        $moderationConfig = $this->configService->getModerationConfig();
        $provider = $options['provider'] ?? $moderationConfig['provider'];
        $model = $options['model'] ?? $moderationConfig['model'];
        $metadata = $options['metadata'] ?? [];
        $providerOptions = $options['provider_options'] ?? [];

        try {
            // Run before_moderate pipeline
            $beforeData = [
                'input' => $input,
                'provider' => $provider,
                'model' => $model,
                'options' => $options,
                'metadata' => $metadata,
            ];

            /** @var array{input: string|array<string>, provider: string, model: string, options: array<string, mixed>, metadata: array<string, mixed>} $beforeData */
            $beforeData = $this->pipelineRunner->runIfActive(
                'moderation.before_moderate',
                $beforeData,
            );

            $input = $beforeData['input'];
            $provider = $beforeData['provider'];
            $model = $beforeData['model'];

            // Use explicit retry config or fall back to config-based retry
            $retry = $retry ?? $this->configService->getRetryConfig();

            $request = $this->prismBuilder->forModeration($provider, $model, $input, $providerOptions, $retry);
            $response = $request->asModeration();

            $result = ModerationResponse::fromPrismResponse($response);

            // Run after_moderate pipeline
            $afterData = [
                'input' => $input,
                'provider' => $provider,
                'model' => $model,
                'options' => $options,
                'metadata' => $metadata,
                'result' => $result,
            ];

            /** @var array{result: ModerationResponse} $afterData */
            $afterData = $this->pipelineRunner->runIfActive(
                'moderation.after_moderate',
                $afterData,
            );

            return $afterData['result'];
        } catch (Throwable $e) {
            $converted = $this->convertPrismException($e);
            $this->handleError($input, $provider, $model, $metadata, $converted);
            throw $converted;
        }
    }

    /**
     * Handle an error by running the error pipeline.
     *
     * @param  string|array<string>  $input  The input that was used.
     * @param  string  $provider  The provider that was used.
     * @param  string  $model  The model that was used.
     * @param  array<string, mixed>  $metadata  The metadata that was provided.
     * @param  Throwable  $exception  The exception that occurred.
     */
    protected function handleError(
        string|array $input,
        string $provider,
        string $model,
        array $metadata,
        Throwable $exception,
    ): void {
        $errorData = [
            'input' => $input,
            'provider' => $provider,
            'model' => $model,
            'metadata' => $metadata,
            'exception' => $exception,
        ];

        $this->pipelineRunner->runIfActive('moderation.on_error', $errorData);
    }
}
