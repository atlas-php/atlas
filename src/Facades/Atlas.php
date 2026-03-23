<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Facades;

use Atlasphp\Atlas\AtlasManager;
use Atlasphp\Atlas\Enums\Provider;
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
use Atlasphp\Atlas\Persistence\Memory\MemoryBuilder;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Testing\AtlasFake;
use Atlasphp\Atlas\Testing\AudioResponseFake;
use Atlasphp\Atlas\Testing\EmbeddingsResponseFake;
use Atlasphp\Atlas\Testing\ImageResponseFake;
use Atlasphp\Atlas\Testing\ModerationResponseFake;
use Atlasphp\Atlas\Testing\RerankResponseFake;
use Atlasphp\Atlas\Testing\StreamResponseFake;
use Atlasphp\Atlas\Testing\StructuredResponseFake;
use Atlasphp\Atlas\Testing\TextResponseFake;
use Atlasphp\Atlas\Testing\VideoResponseFake;
use Atlasphp\Atlas\Testing\VoiceSessionFake;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for the Atlas manager.
 *
 * @method static TextRequest text(Provider|string|null $provider = null, ?string $model = null)
 * @method static ImageRequest image(Provider|string|null $provider = null, ?string $model = null)
 * @method static AudioRequest audio(Provider|string|null $provider = null, ?string $model = null)
 * @method static MusicRequest music(Provider|string|null $provider = null, ?string $model = null)
 * @method static SfxRequest sfx(Provider|string|null $provider = null, ?string $model = null)
 * @method static SpeechRequest speech(Provider|string|null $provider = null, ?string $model = null)
 * @method static VideoRequest video(Provider|string|null $provider = null, ?string $model = null)
 * @method static EmbedRequest embed(Provider|string|null $provider = null, ?string $model = null)
 * @method static ModerateRequest moderate(Provider|string|null $provider = null, ?string $model = null)
 * @method static VoiceRequest voice(Provider|string|null $provider = null, ?string $model = null)
 * @method static RerankRequest rerank(Provider|string|null $provider = null, ?string $model = null)
 * @method static ProviderRequest provider(Provider|string $provider)
 * @method static AgentRequest agent(string $key)
 * @method static MemoryBuilder memory()
 * @method static ProviderRegistryContract providers()
 *
 * @see AtlasManager
 */
class Atlas extends Facade
{
    /**
     * Replace the bound instance with an AtlasFake for testing.
     *
     * @param  array<int, TextResponseFake|StreamResponseFake|StructuredResponseFake|ImageResponseFake|AudioResponseFake|VideoResponseFake|EmbeddingsResponseFake|ModerationResponseFake|RerankResponseFake|VoiceSessionFake>  $responses
     */
    public static function fake(array $responses = []): AtlasFake
    {
        $fake = new AtlasFake(
            app(ProviderRegistryContract::class),
            $responses,
        );

        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return AtlasManager::class;
    }
}
