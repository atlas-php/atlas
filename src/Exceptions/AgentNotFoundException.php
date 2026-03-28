<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Exceptions;

/**
 * Thrown when an agent key cannot be resolved from the registry.
 */
class AgentNotFoundException extends AtlasException
{
    public function __construct(string $key)
    {
        parent::__construct("Agent [{$key}] is not registered. Register it manually or check auto-discovery config.");
    }
}
