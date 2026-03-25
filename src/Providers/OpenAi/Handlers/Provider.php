<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\OpenAi\Handlers;

use Atlasphp\Atlas\Providers\Handlers\AbstractProviderHandler;
use Atlasphp\Atlas\Providers\OpenAi\Concerns\HasOrganizationHeader;
use Atlasphp\Atlas\Providers\VoiceList;

/**
 * OpenAI provider handler for metadata endpoints.
 */
class Provider extends AbstractProviderHandler
{
    use HasOrganizationHeader;

    protected function fetchVoices(): VoiceList
    {
        return new VoiceList([
            'alloy', 'ash', 'ballad', 'cedar', 'coral', 'echo',
            'fable', 'marin', 'nova', 'onyx', 'sage', 'shimmer', 'verse',
        ]);
    }
}
