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
     * Ensure every attached provider-native tool is supported by the resolved provider.
     *
     * Fails fast with a clear Atlas exception instead of letting an incompatible
     * tool reach the provider API and surface as a cryptic HTTP 400.
     *
     * Only first-party providers tracked in ProviderToolRegistry are validated.
     * Custom / OpenAI-compatible providers the registry has no entry for are left
     * untouched — Atlas has no authority to reject their passthrough tools.
     *
     * @param  array<int, ProviderTool>  $providerTools
     *
     * @throws UnsupportedFeatureException
     */
    protected function ensureProviderToolsSupported(Driver $driver, array $providerTools): void
    {
        if ($providerTools === [] || ProviderToolRegistry::forProvider($driver->name()) === []) {
            return;
        }

        foreach ($providerTools as $tool) {
            if (! ProviderToolRegistry::supports($driver->name(), $tool->type())) {
                throw UnsupportedFeatureException::providerTool($tool->type(), $driver->name());
            }
        }
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
