<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Support;

/**
 * Trait for services that support provider, model, and provider options override.
 *
 * Provides fluent withProvider() and withProviderOptions() methods for overriding
 * the configured provider/model and passing provider-specific options at runtime.
 * Uses the clone pattern for immutability.
 */
trait HasProviderSupport
{
    /**
     * Provider override.
     */
    private ?string $providerOverride = null;

    /**
     * Model override.
     */
    private ?string $modelOverride = null;

    /**
     * Provider-specific options to pass through to Prism.
     *
     * @var array<string, mixed>
     */
    private array $providerOptions = [];

    /**
     * Override the provider and optionally the model for this request.
     *
     * @param  string  $provider  The provider name (e.g., 'openai', 'anthropic').
     * @param  string|null  $model  Optional model name (e.g., 'gpt-4', 'dall-e-3').
     */
    public function withProvider(string $provider, ?string $model = null): static
    {
        $clone = clone $this;
        $clone->providerOverride = $provider;

        if ($model !== null) {
            $clone->modelOverride = $model;
        }

        return $clone;
    }

    /**
     * Override the model for this request.
     *
     * @param  string  $model  The model name (e.g., 'gpt-4', 'dall-e-3').
     */
    public function withModel(string $model): static
    {
        $clone = clone $this;
        $clone->modelOverride = $model;

        return $clone;
    }

    /**
     * Set provider-specific options.
     *
     * These options are passed directly to the provider via Prism's withProviderOptions().
     * Use this for provider-specific features like style, response_format, language, etc.
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
     * Get the provider override.
     */
    protected function getProviderOverride(): ?string
    {
        return $this->providerOverride;
    }

    /**
     * Get the model override.
     */
    protected function getModelOverride(): ?string
    {
        return $this->modelOverride;
    }

    /**
     * Get the provider-specific options.
     *
     * @return array<string, mixed>
     */
    protected function getProviderOptions(): array
    {
        return $this->providerOptions;
    }
}
