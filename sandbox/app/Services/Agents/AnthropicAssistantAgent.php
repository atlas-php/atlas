<?php

declare(strict_types=1);

namespace App\Services\Agents;

use Atlasphp\Atlas\Agents\AgentDefinition;

/**
 * Anthropic-based assistant agent for testing.
 *
 * Tests Anthropic Claude models and provider tools like web search.
 */
class AnthropicAssistantAgent extends AgentDefinition
{
    /**
     * Get the AI provider for this agent.
     */
    public function provider(): string
    {
        return 'anthropic';
    }

    /**
     * Get the model to use for this agent.
     */
    public function model(): string
    {
        return 'claude-sonnet-4-20250514';
    }

    /**
     * Get the system prompt for this agent.
     */
    public function systemPrompt(): string
    {
        return 'You are a helpful assistant powered by Anthropic Claude. '
            .'Be concise and helpful in your responses.';
    }

    /**
     * Get a description of this agent.
     */
    public function description(): ?string
    {
        return 'An assistant powered by Anthropic Claude for testing.';
    }

    /**
     * Get the maximum steps for tool use iterations.
     */
    public function maxSteps(): ?int
    {
        return 5;
    }
}
