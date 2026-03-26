<?php

declare(strict_types=1);

namespace Atlasphp\Atlas;

use Atlasphp\Atlas\Agents\AgentRegistry;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Pending\AgentRequest;
use Atlasphp\Atlas\Pending\AudioRequest;
use Atlasphp\Atlas\Pending\EmbedRequest;
use Atlasphp\Atlas\Pending\ImageRequest;
use Atlasphp\Atlas\Pending\ModerateRequest;
use Atlasphp\Atlas\Pending\MusicRequest;
use Atlasphp\Atlas\Pending\ProviderRequest;
use Atlasphp\Atlas\Pending\RerankRequest;
use Atlasphp\Atlas\Pending\SfxRequest;
use Atlasphp\Atlas\Pending\SpeechRequest;
use Atlasphp\Atlas\Pending\TextRequest;
use Atlasphp\Atlas\Pending\VideoRequest;
use Atlasphp\Atlas\Pending\VoiceRequest;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;

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
        private readonly Application $app,
    ) {}

    public function text(Provider|string|null $provider = null, ?string $model = null): TextRequest
    {
        [$provider, $model] = $this->resolveDefaults('text', $provider, $model);

        return new TextRequest(
            $provider,
            $model,
            $this->providerRegistry,
            $this->app,
            $this->app->make(Dispatcher::class),
        );
    }

    public function image(Provider|string|null $provider = null, ?string $model = null): ImageRequest
    {
        [$provider, $model] = $this->resolveDefaults('image', $provider, $model);

        return new ImageRequest($provider, $model, $this->providerRegistry);
    }

    public function audio(Provider|string|null $provider = null, ?string $model = null): AudioRequest
    {
        [$provider, $model] = $this->resolveDefaults('audio', $provider, $model);

        return new AudioRequest($provider, $model, $this->providerRegistry);
    }

    public function music(Provider|string|null $provider = null, ?string $model = null): MusicRequest
    {
        [$provider, $model] = $this->resolveDefaults('music', $provider, $model);

        return new MusicRequest($provider, $model, $this->providerRegistry);
    }

    public function sfx(Provider|string|null $provider = null, ?string $model = null): SfxRequest
    {
        [$provider, $model] = $this->resolveDefaults('sfx', $provider, $model);

        return new SfxRequest($provider, $model, $this->providerRegistry);
    }

    public function speech(Provider|string|null $provider = null, ?string $model = null): SpeechRequest
    {
        [$provider, $model] = $this->resolveDefaults('speech', $provider, $model);

        return new SpeechRequest($provider, $model, $this->providerRegistry);
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

    public function voice(Provider|string|null $provider = null, ?string $model = null): VoiceRequest
    {
        [$provider, $model] = $this->resolveDefaults('voice', $provider, $model);

        return new VoiceRequest($provider, $model, $this->providerRegistry);
    }

    public function provider(Provider|string $provider): ProviderRequest
    {
        return new ProviderRequest($provider, $this->providerRegistry);
    }

    public function agent(string $key): AgentRequest
    {
        return new AgentRequest(
            key: $key,
            agentRegistry: $this->app->make(AgentRegistry::class),
            providerRegistry: $this->providerRegistry,
            app: $this->app,
            events: $this->app->make(Dispatcher::class),
        );
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
