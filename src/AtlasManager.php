<?php

declare(strict_types=1);

namespace Atlasphp\Atlas;

use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Pending\AgentRequest;
use Atlasphp\Atlas\Pending\AudioRequest;
use Atlasphp\Atlas\Pending\EmbedRequest;
use Atlasphp\Atlas\Pending\ImageRequest;
use Atlasphp\Atlas\Pending\ModerateRequest;
use Atlasphp\Atlas\Pending\ProviderRequest;
use Atlasphp\Atlas\Pending\RerankRequest;
use Atlasphp\Atlas\Pending\TextRequest;
use Atlasphp\Atlas\Pending\VideoRequest;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;

/**
 * Central manager for Atlas, accessible via the Atlas facade.
 *
 * Provides entry points for all modalities and the provider registry.
 * Each modality accepts optional provider and model arguments; if omitted,
 * defaults from config('atlas.defaults.*') are used.
 */
class AtlasManager
{
    public function __construct(
        private readonly ProviderRegistryContract $providerRegistry,
    ) {}

    public function text(Provider|string|null $provider = null, ?string $model = null): TextRequest
    {
        [$provider, $model] = $this->resolveDefaults('text', $provider, $model);

        return new TextRequest($provider, $model, $this->providerRegistry);
    }

    public function image(Provider|string|null $provider = null, ?string $model = null): ImageRequest
    {
        [$provider, $model] = $this->resolveDefaults('image', $provider, $model);

        return new ImageRequest($provider, $model, $this->providerRegistry);
    }

    public function audio(Provider|string|null $provider = null, ?string $model = null): AudioRequest
    {
        return new AudioRequest($provider, $model, $this->providerRegistry);
    }

    public function video(Provider|string|null $provider = null, ?string $model = null): VideoRequest
    {
        [$provider, $model] = $this->resolveDefaults('video', $provider, $model);

        return new VideoRequest($provider, $model, $this->providerRegistry);
    }

    public function embed(Provider|string|null $provider = null, ?string $model = null): EmbedRequest
    {
        [$provider, $model] = $this->resolveDefaults('embed', $provider, $model);

        return new EmbedRequest($provider, $model, $this->providerRegistry);
    }

    public function moderate(Provider|string|null $provider = null, ?string $model = null): ModerateRequest
    {
        [$provider, $model] = $this->resolveDefaults('moderate', $provider, $model);

        return new ModerateRequest($provider, $model, $this->providerRegistry);
    }

    public function rerank(Provider|string|null $provider = null, ?string $model = null): RerankRequest
    {
        [$provider, $model] = $this->resolveDefaults('rerank', $provider, $model);

        return new RerankRequest($provider, $model, $this->providerRegistry);
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

    /**
     * Resolve provider and model from explicit arguments or modality defaults.
     *
     * @return array{0: Provider|string, 1: ?string}
     */
    protected function resolveDefaults(string $modality, Provider|string|null $provider, ?string $model): array
    {
        /** @var array<string, string|null> $defaults */
        $defaults = config("atlas.defaults.{$modality}", []);

        $provider = $provider ?? ($defaults['provider'] ?? null);
        $model = $model ?? ($defaults['model'] ?? null);

        if ($provider === null) {
            throw AtlasException::missingDefault($modality);
        }

        return [$provider, $model];
    }
}
