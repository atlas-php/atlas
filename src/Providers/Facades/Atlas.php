<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Facades;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Support\PendingAgentRequest;
use Atlasphp\Atlas\Providers\Services\AtlasManager;
use Atlasphp\Atlas\Providers\Support\PendingEmbeddingRequest;
use Atlasphp\Atlas\Providers\Support\PendingImageRequest;
use Atlasphp\Atlas\Providers\Support\PendingSpeechRequest;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for Atlas functionality.
 *
 * @method static PendingAgentRequest agent(string|AgentContract $agent)
 * @method static PendingEmbeddingRequest embeddings()
 * @method static PendingImageRequest image(?string $provider = null, ?string $model = null)
 * @method static PendingSpeechRequest speech(?string $provider = null, ?string $model = null)
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
