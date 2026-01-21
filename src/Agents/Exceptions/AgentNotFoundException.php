<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Exceptions;

/**
 * Exception thrown when an agent cannot be found.
 *
 * Provides static factory methods for agent not found scenarios.
 */
class AgentNotFoundException extends AgentException
{
    /**
     * Create an exception for agent not found by key.
     *
     * @param  string  $key  The agent key that was not found.
     */
    public static function forKey(string $key): self
    {
        return new self("No agent found with key '{$key}'.");
    }

    /**
     * Create an exception for agent not found by class.
     *
     * @param  string  $class  The agent class that was not found.
     */
    public static function forClass(string $class): self
    {
        return new self("Agent class '{$class}' not found or could not be instantiated.");
    }
}
