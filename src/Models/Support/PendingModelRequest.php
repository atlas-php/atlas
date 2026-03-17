<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Models\Support;

use Atlasphp\Atlas\Models\Services\ListModelsService;
use Prism\Prism\Enums\Provider;

/**
 * Fluent interface for provider model listing operations.
 *
 * Wraps ListModelsService with a clean, provider-scoped API.
 * The provider is always set — operations are scoped to that provider.
 */
class PendingModelRequest
{
    public function __construct(
        protected ListModelsService $service,
        protected Provider|string $provider,
    ) {}

    /**
     * Get all models for the provider (cached or fresh).
     *
     * @return list<array{id: string, name: string|null}>|null
     */
    public function all(): ?array
    {
        return $this->service->get($this->provider);
    }

    /**
     * Check if the provider supports model listing.
     */
    public function has(): bool
    {
        return $this->service->has($this->provider);
    }

    /**
     * Force refresh models from the provider API.
     *
     * @return list<array{id: string, name: string|null}>|null
     */
    public function refresh(): ?array
    {
        return $this->service->refresh($this->provider);
    }

    /**
     * Clear cached models for the provider.
     */
    public function clear(): void
    {
        $this->service->clear($this->provider);
    }
}
