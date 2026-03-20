<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\ChatCompletions\Handlers;

use Atlasphp\Atlas\Providers\Handlers\AbstractProviderHandler;
use Atlasphp\Atlas\Providers\VoiceList;

/**
 * Provider handler for Chat Completions compatible endpoints.
 *
 * Models endpoint is standardized at /v1/models.
 * Voices are provider-specific and not discoverable.
 */
class Provider extends AbstractProviderHandler
{
    public function voices(): VoiceList
    {
        return new VoiceList([]);
    }
}
