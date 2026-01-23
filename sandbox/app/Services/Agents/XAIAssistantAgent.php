<?php

declare(strict_types=1);

namespace App\Services\Agents;

use Atlasphp\Atlas\Agents\AgentDefinition;

/**
 * xAI (Grok) based assistant agent for testing.
 *
 * Tests xAI Grok models.
 */
class XAIAssistantAgent extends AgentDefinition
{
    /**
     * Get the AI provider for this agent.
     */
    public function provider(): ?string
    {
        return 'xai';
    }

    /**
     * Get the model to use for this agent.
     */
    public function model(): ?string
    {
        return 'grok-2-latest';
    }

    /**
     * Get the system prompt for this agent.
     */
    public function systemPrompt(): ?string
    {
        return 'You are a helpful assistant powered by xAI Grok. '
            .'Be concise and helpful in your responses.';
    }

    /**
     * Get a description of this agent.
     */
    public function description(): ?string
    {
        return 'An assistant powered by xAI Grok for testing.';
    }

    /**
     * Get the maximum steps for tool use iterations.
     */
    public function maxSteps(): ?int
    {
        return 5;
    }
}
