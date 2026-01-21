<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Facades;

use Atlasphp\Atlas\Providers\Services\AtlasManager;
use Atlasphp\Atlas\Providers\Services\ImageService;
use Atlasphp\Atlas\Providers\Services\SpeechService;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for Atlas functionality.
 *
 * @method static array<int, float> embed(string $text)
 * @method static array<int, array<int, float>> embedBatch(array<int, string> $texts)
 * @method static int embeddingDimensions()
 * @method static ImageService image()
 * @method static SpeechService speech()
 *
 * @see \Atlasphp\Atlas\Providers\Services\AtlasManager
 */
class Atlas extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return AtlasManager::class;
    }
}
