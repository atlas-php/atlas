<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Exceptions;

/**
 * Thrown when a tool cannot be found in the registry.
 */
class ToolNotFoundException extends AtlasException
{
    public function __construct(string $name)
    {
        parent::__construct("Tool [{$name}] is not registered.");
    }
}
