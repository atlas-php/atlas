<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Support;

/**
 * Trait for services that support provider and model override.
 *
 * Provides fluent withProvider() and withModel() methods for overriding
 * the configured provider and model at runtime. Uses the clone pattern
 * for immutability.
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
     * Override the provider for this request.
     *
     * @param  string  $provider  The provider name (e.g., 'openai', 'anthropic').
     */
    public function withProvider(string $provider): static
    {
        $clone = clone $this;
        $clone->providerOverride = $provider;

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
}
