<?php

declare(strict_types=1);

namespace Atlasphp\Atlas;

use Atlasphp\Atlas\Contracts\ProviderRegistryContract;

/**
 * Central manager for Atlas, accessible via the Atlas facade.
 *
 * Provides access to the provider registry and will gain additional
 * capabilities (text, image, agent) in future phases.
 */
class AtlasManager
{
    public function __construct(
        private readonly ProviderRegistryContract $providerRegistry,
    ) {}

    /**
     * Get the provider registry.
     */
    public function providers(): ProviderRegistryContract
    {
        return $this->providerRegistry;
    }
}
