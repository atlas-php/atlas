<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Tools;

/**
 * Web fetch provider tool configuration.
 */
class WebFetch extends ProviderTool
{
    public function type(): string
    {
        return 'web_fetch';
    }
}
