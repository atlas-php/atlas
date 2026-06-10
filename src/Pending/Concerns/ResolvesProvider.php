<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending\Concerns;

use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\Tools\ProviderTool;
use Atlasphp\Atlas\Providers\Tools\ProviderToolRegistry;

/**
 * Shared driver resolution and capability checking for Pending request classes.
 *
 * Expects the using class to have $provider (Provider|string) and $registry (ProviderRegistryContract) properties.
 */
trait ResolvesProvider
{
    protected function resolveDriver(): Driver
    {
        return $this->registry->resolve(Provider::normalize($this->provider));
    }

    /**
     * @throws UnsupportedFeatureException
     */
    protected function ensureCapability(Driver $driver, string $feature): void
    {
        if (! $driver->capabilities()->supports($feature)) {
            throw UnsupportedFeatureException::make($feature, $driver->name());
        }
    }

    /**
     * Fail fast if any attached provider tool is unsupported by the resolved provider.
     *
     * Short-circuits on no tools so the provider name is only resolved when there
     * is actually something to validate. Matching is delegated to the registry.
     *
     * @param  array<int, ProviderTool>  $providerTools
     *
     * @throws UnsupportedFeatureException
     */
    protected function ensureProviderToolsSupported(Driver $driver, array $providerTools): void
    {
        if ($providerTools === []) {
            return;
        }

        ProviderToolRegistry::assertSupported($driver->name(), $providerTools);
    }

    /**
     * Resolve the provider as a string key for events and queue serialization.
     */
    protected function resolveProviderKey(): string
    {
        return Provider::normalize($this->provider);
    }

    /**
     * Resolve the model as a string key for events and queue serialization.
     */
    protected function resolveModelKey(): string
    {
        return (string) $this->model;
    }
}
