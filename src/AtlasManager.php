<?php

declare(strict_types=1);

namespace Atlasphp\Atlas;

use Atlasphp\Atlas\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Pending\AgentRequest;
use Atlasphp\Atlas\Pending\AudioRequest;
use Atlasphp\Atlas\Pending\EmbedRequest;
use Atlasphp\Atlas\Pending\ImageRequest;
use Atlasphp\Atlas\Pending\ModerateRequest;
use Atlasphp\Atlas\Pending\ProviderRequest;
use Atlasphp\Atlas\Pending\TextRequest;
use Atlasphp\Atlas\Pending\VideoRequest;

/**
 * Central manager for Atlas, accessible via the Atlas facade.
 *
 * Provides entry points for all modalities and the provider registry.
 */
class AtlasManager
{
    public function __construct(
        private readonly ProviderRegistryContract $providerRegistry,
    ) {}

    public function text(Provider|string $provider, string $model): TextRequest
    {
        return new TextRequest($provider, $model, $this->providerRegistry);
    }

    public function image(Provider|string $provider, string $model): ImageRequest
    {
        return new ImageRequest($provider, $model, $this->providerRegistry);
    }

    public function audio(Provider|string $provider, string $model): AudioRequest
    {
        return new AudioRequest($provider, $model, $this->providerRegistry);
    }

    public function video(Provider|string $provider, string $model): VideoRequest
    {
        return new VideoRequest($provider, $model, $this->providerRegistry);
    }

    public function embed(Provider|string $provider, string $model): EmbedRequest
    {
        return new EmbedRequest($provider, $model, $this->providerRegistry);
    }

    public function moderate(Provider|string $provider, string $model): ModerateRequest
    {
        return new ModerateRequest($provider, $model, $this->providerRegistry);
    }

    public function provider(Provider|string $provider): ProviderRequest
    {
        return new ProviderRequest($provider, $this->providerRegistry);
    }

    public function agent(string $key): AgentRequest
    {
        return new AgentRequest($key);
    }

    /**
     * Get the provider registry.
     */
    public function providers(): ProviderRegistryContract
    {
        return $this->providerRegistry;
    }
}
