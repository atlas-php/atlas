<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending\Concerns;

use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Providers\Driver;

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
     * Resolve the provider as a string key for events and queue serialization.
     */
    protected function resolveProviderKey(): string
    {
        return Provider::normalize($this->provider);
    }
}
