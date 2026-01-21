<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Exceptions;

/**
 * Exception thrown when an agent is invalid or misconfigured.
 *
 * Provides static factory methods for invalid agent scenarios.
 */
class InvalidAgentException extends AgentException
{
    /**
     * Create an exception for agent that does not implement the contract.
     *
     * @param  string  $class  The class name.
     */
    public static function doesNotImplementContract(string $class): self
    {
        return new self("Class '{$class}' does not implement AgentContract.");
    }

    /**
     * Create an exception for missing required configuration.
     *
     * @param  string  $agentKey  The agent key.
     * @param  string  $field  The missing field.
     */
    public static function missingRequired(string $agentKey, string $field): self
    {
        return new self("Agent '{$agentKey}' is missing required field: {$field}");
    }

    /**
     * Create an exception for invalid provider.
     *
     * @param  string  $agentKey  The agent key.
     * @param  string  $provider  The invalid provider.
     */
    public static function invalidProvider(string $agentKey, string $provider): self
    {
        return new self("Agent '{$agentKey}' has invalid provider: {$provider}");
    }
}
