<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Enums\AgentType;

/**
 * Base class for agent definitions.
 *
 * Provides sensible defaults for optional agent configuration.
 * Extend this class to create custom agents with minimal boilerplate.
 */
abstract class AgentDefinition implements AgentContract
{
    /**
     * Get the unique key identifying this agent.
     *
     * Defaults to the class name in kebab-case.
     */
    public function key(): string
    {
        $class = (new \ReflectionClass($this))->getShortName();

        // Remove common suffixes
        $class = preg_replace('/Agent$/', '', $class) ?? $class;

        // Convert to kebab-case
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $class) ?? $class);
    }

    /**
     * Get the display name for this agent.
     *
     * Defaults to the class name with spaces.
     */
    public function name(): string
    {
        $class = (new \ReflectionClass($this))->getShortName();

        // Remove common suffixes
        $class = preg_replace('/Agent$/', '', $class) ?? $class;

        // Convert to words
        return trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $class) ?? $class);
    }

    /**
     * Get the agent execution type.
     *
     * Defaults to API execution. Override for CLI agents.
     */
    public function type(): AgentType
    {
        return AgentType::Api;
    }

    /**
     * Get the optional agent description.
     */
    public function description(): ?string
    {
        return null;
    }

    /**
     * Get the tool classes available to this agent.
     *
     * @return array<int, class-string>
     */
    public function tools(): array
    {
        return [];
    }

    /**
     * Get provider-specific tools (e.g., 'web_search', 'code_execution').
     *
     * Return simple tool names or arrays with options:
     *   ['web_search']
     *   [['type' => 'web_search', 'max_results' => 5]]
     *
     * @return array<int, string|array{type: string, ...}>
     */
    public function providerTools(): array
    {
        return [];
    }

    /**
     * Get the temperature setting.
     */
    public function temperature(): ?float
    {
        return null;
    }

    /**
     * Get the max tokens setting.
     */
    public function maxTokens(): ?int
    {
        return null;
    }

    /**
     * Get the max steps for tool use iterations.
     */
    public function maxSteps(): ?int
    {
        return null;
    }

    /**
     * Get additional provider-specific settings.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return [];
    }
}
