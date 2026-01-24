<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Contracts;

use Atlasphp\Atlas\Agents\Enums\AgentType;
use Prism\Prism\Contracts\Schema;

/**
 * Contract for agent definitions.
 *
 * Defines the configuration interface that all agents must implement.
 * Agents specify their provider, model, system prompt, and available tools.
 */
interface AgentContract
{
    /**
     * Get the unique key identifying this agent.
     */
    public function key(): string;

    /**
     * Get the display name for this agent.
     */
    public function name(): string;

    /**
     * Get the agent execution type.
     */
    public function type(): AgentType;

    /**
     * Get the AI provider name (e.g., 'openai', 'anthropic').
     *
     * Returns null to use the default provider from config.
     */
    public function provider(): ?string;

    /**
     * Get the model name to use.
     *
     * Returns null to use the default model from config.
     */
    public function model(): ?string;

    /**
     * Get the system prompt template.
     *
     * May contain {variable} placeholders for interpolation.
     * Returns null to skip the system prompt entirely (uses provider default).
     */
    public function systemPrompt(): ?string;

    /**
     * Get the optional agent description.
     */
    public function description(): ?string;

    /**
     * Get the tool classes available to this agent.
     *
     * @return array<int, class-string>
     */
    public function tools(): array;

    /**
     * Get provider-specific tools (e.g., 'web_search', 'code_execution').
     *
     * Can be a simple list of tool names:
     *   ['web_search', 'code_execution']
     *
     * Or with options:
     *   [
     *       'web_search',
     *       ['type' => 'code_execution', 'container' => 'python'],
     *   ]
     *
     * @return array<int, string|array{type: string, ...}>
     */
    public function providerTools(): array;

    /**
     * Get the temperature setting.
     */
    public function temperature(): ?float;

    /**
     * Get the max tokens setting.
     */
    public function maxTokens(): ?int;

    /**
     * Get the max steps for tool use iterations.
     */
    public function maxSteps(): ?int;

    /**
     * Get HTTP client options (timeout, retries, etc.).
     *
     * These are passed to Prism's withClientOptions().
     *
     * @return array<string, mixed>
     */
    public function clientOptions(): array;

    /**
     * Get provider-specific options.
     *
     * These are passed to Prism's withProviderOptions().
     *
     * @return array<string, mixed>
     */
    public function providerOptions(): array;

    /**
     * Get the schema for structured output.
     *
     * When defined, the agent will use Prism's structured output module
     * and return a StructuredResponse instead of a text Response.
     *
     * Returns null to use text output (default behavior).
     * Can be overridden at call time via withSchema().
     */
    public function schema(): ?Schema;
}
