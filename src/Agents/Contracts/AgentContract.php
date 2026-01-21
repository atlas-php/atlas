<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Contracts;

use Atlasphp\Atlas\Agents\Enums\AgentType;

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
     */
    public function provider(): string;

    /**
     * Get the model name to use.
     */
    public function model(): string;

    /**
     * Get the system prompt template.
     *
     * May contain {variable} placeholders for interpolation.
     */
    public function systemPrompt(): string;

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
     * @return array<int, string>
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
     * Get additional provider-specific settings.
     *
     * @return array<string, mixed>
     */
    public function settings(): array;
}
