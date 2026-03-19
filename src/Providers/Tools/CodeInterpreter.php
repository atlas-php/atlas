<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Tools;

/**
 * Code interpreter provider tool configuration.
 */
class CodeInterpreter extends ProviderTool
{
    public function type(): string
    {
        return 'code_interpreter';
    }
}
