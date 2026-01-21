<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Facades;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Providers\Services\AtlasManager;
use Atlasphp\Atlas\Providers\Services\ImageService;
use Atlasphp\Atlas\Providers\Services\SpeechService;
use Atlasphp\Atlas\Providers\Support\MessageContextBuilder;
use Illuminate\Support\Facades\Facade;
use Prism\Prism\Contracts\Schema;

/**
 * Facade for Atlas functionality.
 *
 * @method static AgentResponse chat(string|AgentContract $agent, string $input, ?array<int, array{role: string, content: string}> $messages = null, ?Schema $schema = null)
 * @method static MessageContextBuilder forMessages(array<int, array{role: string, content: string}> $messages)
 * @method static array<int, float> embed(string $text)
 * @method static array<int, array<int, float>> embedBatch(array<int, string> $texts)
 * @method static int embeddingDimensions()
 * @method static ImageService image(?string $provider = null, ?string $model = null)
 * @method static SpeechService speech(?string $provider = null, ?string $model = null)
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
