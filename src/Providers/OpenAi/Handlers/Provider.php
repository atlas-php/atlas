<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\OpenAi\Handlers;

use Atlasphp\Atlas\Providers\Handlers\AbstractProviderHandler;
use Atlasphp\Atlas\Providers\OpenAi\HasOrganizationHeader;
use Atlasphp\Atlas\Providers\VoiceList;

/**
 * OpenAI provider handler for metadata endpoints.
 */
class Provider extends AbstractProviderHandler
{
    use HasOrganizationHeader;

    public function voices(): VoiceList
    {
        return new VoiceList([
            'alloy', 'ash', 'ballad', 'coral', 'echo',
            'fable', 'onyx', 'nova', 'sage', 'shimmer',
        ]);
    }
}
