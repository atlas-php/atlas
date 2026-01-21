<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Exceptions;

use Exception;

/**
 * Base exception for tool-related errors.
 *
 * Provides static factory methods for common tool error scenarios.
 */
class ToolException extends Exception
{
    /**
     * Create an exception for tool execution failures.
     *
     * @param  string  $toolName  The tool name.
     * @param  string  $reason  The failure reason.
     */
    public static function executionFailed(string $toolName, string $reason): self
    {
        return new self("Tool '{$toolName}' execution failed: {$reason}");
    }

    /**
     * Create an exception for invalid tool configuration.
     *
     * @param  string  $toolName  The tool name.
     * @param  string  $message  The configuration error message.
     */
    public static function invalidConfiguration(string $toolName, string $message): self
    {
        return new self("Tool '{$toolName}' has invalid configuration: {$message}");
    }

    /**
     * Create an exception for duplicate tool registration.
     *
     * @param  string  $name  The duplicate name.
     */
    public static function duplicateRegistration(string $name): self
    {
        return new self("A tool with name '{$name}' has already been registered.");
    }

    /**
     * Create an exception for invalid parameter.
     *
     * @param  string  $toolName  The tool name.
     * @param  string  $paramName  The invalid parameter name.
     * @param  string  $reason  The reason it's invalid.
     */
    public static function invalidParameter(string $toolName, string $paramName, string $reason): self
    {
        return new self("Tool '{$toolName}' has invalid parameter '{$paramName}': {$reason}");
    }
}
