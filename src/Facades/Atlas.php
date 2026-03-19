<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Facades;

use Atlasphp\Atlas\AtlasManager;
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
use Illuminate\Support\Facades\Facade;

/**
 * Facade for the Atlas manager.
 *
 * @method static TextRequest text(Provider|string $provider, string $model)
 * @method static ImageRequest image(Provider|string $provider, string $model)
 * @method static AudioRequest audio(Provider|string $provider, string $model)
 * @method static VideoRequest video(Provider|string $provider, string $model)
 * @method static EmbedRequest embed(Provider|string $provider, string $model)
 * @method static ModerateRequest moderate(Provider|string $provider, string $model)
 * @method static ProviderRequest provider(Provider|string $provider)
 * @method static AgentRequest agent(string $key)
 * @method static ProviderRegistryContract providers()
 *
 * @see AtlasManager
 */
class Atlas extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AtlasManager::class;
    }
}
