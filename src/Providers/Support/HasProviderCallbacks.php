<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Support;

use Prism\Prism\Enums\Provider as ProviderEnum;

/**
 * Trait for conditional provider-based configuration.
 *
 * Provides a whenProvider() method to apply configuration callbacks
 * only when a specific provider is active. Callbacks are stored and
 * executed at terminal operation time when the provider is resolved.
 */
trait HasProviderCallbacks
{
    /**
     * Provider-specific callbacks keyed by provider name.
     *
     * @var array<string, array<int, callable>>
     */
    private array $providerCallbacks = [];

    /**
     * Register a callback to be executed when a specific provider is active.
     *
     * The callback receives the current request instance and should return
     * a modified instance. Callbacks are executed at terminal operation time
     * (e.g., chat(), generate()) when the provider is resolved.
     *
     * @param  string|ProviderEnum  $provider  The provider name or enum (e.g., 'anthropic', Provider::Anthropic).
     * @param  callable  $callback  A callback that receives the request and returns a modified request.
     */
    public function whenProvider(string|ProviderEnum $provider, callable $callback): static
    {
        $providerKey = $provider instanceof ProviderEnum ? $provider->value : $provider;

        $clone = clone $this;
        $clone->providerCallbacks[$providerKey] ??= [];
        $clone->providerCallbacks[$providerKey][] = $callback;

        return $clone;
    }

    /**
     * Apply all registered callbacks for the resolved provider.
     *
     * @param  string  $resolvedProvider  The resolved provider name.
     * @return static The modified request after applying matching callbacks.
     */
    protected function applyProviderCallbacks(string $resolvedProvider): static
    {
        $request = $this;

        if (isset($this->providerCallbacks[$resolvedProvider])) {
            foreach ($this->providerCallbacks[$resolvedProvider] as $callback) {
                $request = $callback($request);
            }
        }

        return $request;
    }

    /**
     * Get all registered provider callbacks.
     *
     * @return array<string, array<int, callable>>
     */
    protected function getProviderCallbacks(): array
    {
        return $this->providerCallbacks;
    }
}
