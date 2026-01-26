<?php

declare(strict_types=1);

namespace App\Agents;

use App\Tools\CalculatorTool;
use App\Tools\DateTimeTool;
use App\Tools\WeatherTool;
use Atlasphp\Atlas\Agents\AgentDefinition;

/**
 * Full-featured agent demonstrating all tool types.
 *
 * This agent showcases how to combine:
 * - Atlas tools (custom tools defined in app/Tools/)
 * - Provider tools (native provider features like web search)
 * - MCP tools (external tools via Model Context Protocol)
 *
 * Use this as a reference for building complex agents with multiple tool sources.
 */
class FullFeaturedAgent extends AgentDefinition
{
    /**
     * Get the AI provider for this agent.
     */
    public function provider(): ?string
    {
        return 'openai';
    }

    /**
     * Get the model to use for this agent.
     */
    public function model(): ?string
    {
        return 'gpt-4o';
    }

    /**
     * Get the system prompt for this agent.
     *
     * Supports variable interpolation with {variable_name} syntax.
     */
    public function systemPrompt(): ?string
    {
        return <<<'PROMPT'
You are a powerful assistant with access to multiple types of tools:

## Atlas Tools (Custom)
- calculator: Perform mathematical calculations
- weather: Get weather information for any location
- datetime: Get current date/time in any timezone

## Provider Tools
- web_search_preview: Search the web for current information

## MCP Tools (if configured)
Additional tools may be available from connected MCP servers.

When responding to user requests:
1. Use the most appropriate tool for the task
2. Combine tools when needed (e.g., search + calculation)
3. Always provide clear explanations of tool results

Current context:
- User: {user_name}
- Session: {session_id}
PROMPT;
    }

    /**
     * Get a description of this agent.
     */
    public function description(): ?string
    {
        return 'A comprehensive agent demonstrating Atlas tools, provider tools, and MCP tools integration.';
    }

    /**
     * Get the Atlas tool classes available to this agent.
     *
     * These are custom tools defined in app/Tools/ that extend ToolDefinition.
     *
     * @return array<int, class-string>
     */
    public function tools(): array
    {
        return [
            CalculatorTool::class,
            WeatherTool::class,
            DateTimeTool::class,
        ];
    }

    /**
     * Get provider-specific tools.
     *
     * These are native features of the AI provider (e.g., OpenAI's web search).
     * Can be simple strings or arrays with configuration options.
     *
     * @return array<int, string|array{type: string, ...}>
     */
    public function providerTools(): array
    {
        return [
            // Simple string format - uses provider defaults
            'web_search_preview',

            // Array format with options (example)
            // ['type' => 'web_search_preview', 'search_context_size' => 'medium'],
        ];
    }

    /**
     * The Relay facade class name (avoid direct reference for PHPStan).
     */
    private const RELAY_FACADE = 'Prism\\Relay\\Facades\\Relay';

    /**
     * Get MCP tools from external MCP servers.
     *
     * Returns Prism Tool instances, typically from prism-php/relay.
     * These tools connect to external MCP servers for additional capabilities.
     *
     * Example with Relay (requires prism-php/relay package):
     *
     * ```php
     * use Prism\Relay\Facades\Relay;
     *
     * public function mcpTools(): array
     * {
     *     return [
     *         ...Relay::tools('filesystem'),
     *         ...Relay::tools('github'),
     *     ];
     * }
     * ```
     *
     * @return array<int, \Prism\Prism\Tool>
     */
    public function mcpTools(): array
    {
        // Check if Relay facade is available
        if (! class_exists(self::RELAY_FACADE)) {
            return [];
        }

        try {
            // Get tools from configured MCP servers
            $tools = [];

            // Example: filesystem server for file operations
            if ($this->hasMcpServer('filesystem')) {
                /** @var array<int, mixed> $serverTools */
                $serverTools = forward_static_call([self::RELAY_FACADE, 'tools'], 'filesystem');
                $tools = [...$tools, ...$serverTools];
            }

            return $tools;
        } catch (\Throwable) {
            // Return empty array if MCP servers are not available
            return [];
        }
    }

    /**
     * Get the temperature setting.
     *
     * Lower values (0.0-0.3) for focused/deterministic responses.
     * Higher values (0.7-1.0) for creative/varied responses.
     */
    public function temperature(): ?float
    {
        return 0.7;
    }

    /**
     * Get the max tokens setting.
     */
    public function maxTokens(): ?int
    {
        return 4096;
    }

    /**
     * Get the max steps for tool use iterations.
     *
     * This limits how many tool call cycles the agent can perform.
     */
    public function maxSteps(): ?int
    {
        return 10;
    }

    /**
     * Get HTTP client options.
     *
     * @return array<string, mixed>
     */
    public function clientOptions(): array
    {
        return [
            'timeout' => 120,
        ];
    }

    /**
     * Get provider-specific options.
     *
     * @return array<string, mixed>
     */
    public function providerOptions(): array
    {
        return [
            // Provider-specific configuration
            // 'frequency_penalty' => 0.5,
            // 'presence_penalty' => 0.5,
        ];
    }

    /**
     * Check if an MCP server is configured.
     */
    protected function hasMcpServer(string $name): bool
    {
        $servers = config('relay.servers', []);

        return isset($servers[$name]);
    }
}
