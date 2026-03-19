<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Facades;

use Atlasphp\Atlas\AtlasManager;
use Atlasphp\Atlas\Contracts\ProviderRegistryContract;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for the Atlas manager.
 *
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
