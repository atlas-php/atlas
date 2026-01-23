<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Services;

use Atlasphp\Atlas\Providers\Exceptions\ProviderException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Service for accessing provider configuration.
 *
 * Provides a clean API for retrieving provider-specific configuration
 * from the application configuration.
 */
class ProviderConfigService
{
    public function __construct(
        protected ConfigRepository $config,
    ) {}

    /**
     * Get configuration for a specific provider.
     *
     * @return array<string, mixed>
     *
     * @throws ProviderException If provider configuration is missing or invalid.
     */
    public function getProviderConfig(string $provider): array
    {
        $config = $this->config->get("atlas.providers.{$provider}");

        if ($config === null) {
            throw ProviderException::missingConfiguration('provider', $provider);
        }

        if (! is_array($config)) {
            throw ProviderException::invalidConfigurationValue(
                'provider',
                $provider,
                'Configuration must be an array'
            );
        }

        return $config;
    }

    /**
     * Get all configured providers.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getProviders(): array
    {
        return $this->config->get('atlas.providers', []);
    }

    /**
     * Check if a provider has configuration.
     */
    public function hasProvider(string $provider): bool
    {
        return $this->config->has("atlas.providers.{$provider}");
    }

    /**
     * Get the timeout for a provider.
     */
    public function getTimeout(string $provider): int
    {
        return (int) $this->config->get("atlas.providers.{$provider}.timeout", 30);
    }

    /**
     * Get chat configuration.
     *
     * @return array{provider: string, model: string}
     */
    public function getChatConfig(): array
    {
        return [
            'provider' => (string) $this->config->get('atlas.chat.provider', 'openai'),
            'model' => (string) $this->config->get('atlas.chat.model', 'gpt-4o'),
        ];
    }

    /**
     * Get embedding configuration.
     *
     * @return array{provider: string, model: string, dimensions: int, batch_size: int}
     */
    public function getEmbeddingConfig(): array
    {
        return [
            'provider' => (string) $this->config->get('atlas.embedding.provider', 'openai'),
            'model' => (string) $this->config->get('atlas.embedding.model', 'text-embedding-3-small'),
            'dimensions' => (int) $this->config->get('atlas.embedding.dimensions', 1536),
            'batch_size' => (int) $this->config->get('atlas.embedding.batch_size', 100),
        ];
    }

    /**
     * Get image service configuration.
     *
     * @return array{provider: string, model: string}
     */
    public function getImageConfig(): array
    {
        return [
            'provider' => (string) $this->config->get('atlas.image.provider', 'openai'),
            'model' => (string) $this->config->get('atlas.image.model', 'dall-e-3'),
        ];
    }

    /**
     * Get speech service configuration.
     *
     * @return array{provider: string, model: string, transcription_model: string}
     */
    public function getSpeechConfig(): array
    {
        return [
            'provider' => (string) $this->config->get('atlas.speech.provider', 'openai'),
            'model' => (string) $this->config->get('atlas.speech.model', 'tts-1'),
            'transcription_model' => (string) $this->config->get('atlas.speech.transcription_model', 'whisper-1'),
        ];
    }

    /**
     * Get retry configuration as an array suitable for PrismBuilder.
     *
     * Returns null if retry is disabled, otherwise returns [times, sleepMs, when, throw].
     *
     * @return array{0: int, 1: \Closure|int, 2: null, 3: bool}|null
     */
    public function getRetryConfig(): ?array
    {
        $enabled = (bool) $this->config->get('atlas.retry.enabled', false);

        if (! $enabled) {
            return null;
        }

        $times = (int) $this->config->get('atlas.retry.times', 3);
        $strategy = (string) $this->config->get('atlas.retry.strategy', 'fixed');
        $delayMs = (int) $this->config->get('atlas.retry.delay_ms', 1000);

        $sleep = $strategy === 'exponential'
            ? fn (int $attempt): int => (int) ($delayMs * (2 ** ($attempt - 1)))
            : $delayMs;

        return [$times, $sleep, null, true];
    }
}
