<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Exceptions;

/**
 * Exception thrown when a tool cannot be found.
 *
 * Provides static factory methods for tool not found scenarios.
 */
class ToolNotFoundException extends ToolException
{
    /**
     * Create an exception for tool not found by name.
     *
     * @param  string  $name  The tool name that was not found.
     */
    public static function forName(string $name): self
    {
        return new self("No tool found with name '{$name}'.");
    }

    /**
     * Create an exception for tool not found by class.
     *
     * @param  string  $class  The tool class that was not found.
     */
    public static function forClass(string $class): self
    {
        return new self("Tool class '{$class}' not found or could not be instantiated.");
    }
}
