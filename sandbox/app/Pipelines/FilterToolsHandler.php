<?php

declare(strict_types=1);

namespace App\Pipelines;

use Atlasphp\Atlas\Contracts\PipelineContract;
use Closure;

/**
 * Pipeline handler that filters available tools.
 *
 * Demonstrates how to restrict which tools are available to an agent
 * at runtime based on context or configuration.
 */
class FilterToolsHandler implements PipelineContract
{
    /**
     * Tools that are allowed (allowlist).
     *
     * @var array<string>
     */
    protected array $allowedTools = [];

    /**
     * Create a new handler instance.
     *
     * @param  array<string>  $allowedTools  List of tool names to allow (empty = allow all)
     */
    public function __construct(array $allowedTools = [])
    {
        $this->allowedTools = $allowedTools;
    }

    /**
     * Handle the pipeline data.
     *
     * @param  array{agent: mixed, context: mixed, tool_context: mixed, agent_tools: array, agent_mcp_tools: array, tools: array}  $data
     */
    public function handle(mixed $data, Closure $next): mixed
    {
        // If no allowlist specified, pass through unchanged
        if (empty($this->allowedTools)) {
            return $next($data);
        }

        // Filter tools to only those in the allowlist
        $filteredTools = [];
        foreach ($data['tools'] as $tool) {
            $toolName = $this->getToolName($tool);
            if (in_array($toolName, $this->allowedTools, true)) {
                $filteredTools[] = $tool;
            }
        }

        $data['tools'] = $filteredTools;

        return $next($data);
    }

    /**
     * Get the name of a tool.
     */
    protected function getToolName(mixed $tool): string
    {
        // Handle Prism Tool objects
        if (is_object($tool) && method_exists($tool, 'name')) {
            return $tool->name();
        }

        // Handle array format
        if (is_array($tool) && isset($tool['name'])) {
            return $tool['name'];
        }

        return 'unknown';
    }
}
