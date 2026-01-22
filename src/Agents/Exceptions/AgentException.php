<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Exceptions;

use Exception;

/**
 * Base exception for agent-related errors.
 *
 * Provides static factory methods for common agent error scenarios.
 */
class AgentException extends Exception
{
    /**
     * Create an exception for agent execution failures.
     *
     * @param  string  $agentKey  The agent key.
     * @param  string  $reason  The failure reason.
     * @param  \Throwable|null  $previous  The previous exception for chaining.
     */
    public static function executionFailed(string $agentKey, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            "Agent '{$agentKey}' execution failed: {$reason}",
            0,
            $previous,
        );
    }

    /**
     * Create an exception for invalid agent configuration.
     *
     * @param  string  $agentKey  The agent key.
     * @param  string  $message  The configuration error message.
     */
    public static function invalidConfiguration(string $agentKey, string $message): self
    {
        return new self("Agent '{$agentKey}' has invalid configuration: {$message}");
    }

    /**
     * Create an exception for duplicate agent registration.
     *
     * @param  string  $key  The duplicate key.
     */
    public static function duplicateRegistration(string $key): self
    {
        return new self("An agent with key '{$key}' has already been registered.");
    }

    /**
     * Create an exception for resolution failures.
     *
     * @param  string  $identifier  The agent identifier that could not be resolved.
     */
    public static function resolutionFailed(string $identifier): self
    {
        return new self("Failed to resolve agent: {$identifier}");
    }
}
