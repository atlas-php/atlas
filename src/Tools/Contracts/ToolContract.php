<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Contracts;

use Atlasphp\Atlas\Tools\Support\ToolContext;
use Atlasphp\Atlas\Tools\Support\ToolResult;
use Prism\Prism\Contracts\Schema;

/**
 * Contract for tool definitions.
 *
 * Defines the interface that all tools must implement.
 * Tools are callable functions that agents can invoke to perform actions.
 */
interface ToolContract
{
    /**
     * Get the unique name identifying this tool.
     *
     * Should be lowercase with underscores (e.g., 'search_web').
     */
    public function name(): string;

    /**
     * Get the description of what this tool does.
     *
     * Used by the AI to understand when to use this tool.
     */
    public function description(): string;

    /**
     * Get the parameters this tool accepts.
     *
     * @return array<int, Schema>
     */
    public function parameters(): array;

    /**
     * Execute the tool with the given parameters.
     *
     * @param  array<string, mixed>  $params  The parameter values passed by the AI.
     * @param  ToolContext  $context  The execution context.
     */
    public function handle(array $params, ToolContext $context): ToolResult;
}
