<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Xai\Handlers;

use Atlasphp\Atlas\Providers\Handlers\AbstractProviderHandler;
use Atlasphp\Atlas\Providers\VoiceList;

/**
 * xAI provider handler for metadata endpoints.
 */
class Provider extends AbstractProviderHandler
{
    protected function fetchVoices(): VoiceList
    {
        return new VoiceList(['ara', 'eve', 'leo', 'rex', 'sal']);
    }
}
