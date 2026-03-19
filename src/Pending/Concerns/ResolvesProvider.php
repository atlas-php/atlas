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
        $key = $this->provider instanceof Provider
            ? $this->provider->value
            : $this->provider;

        return $this->registry->resolve($key);
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
}
