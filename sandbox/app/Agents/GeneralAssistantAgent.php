<?php

declare(strict_types=1);

namespace App\Agents;

use Atlasphp\Atlas\Agents\AgentDefinition;

/**
 * General-purpose chat agent for sandbox testing.
 *
 * Provides a simple, helpful assistant for interactive chat testing.
 */
class GeneralAssistantAgent extends AgentDefinition
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
     */
    public function systemPrompt(): ?string
    {
        return 'You are a helpful assistant. Be concise and helpful in your responses. '
            .'Keep answers focused and relevant to the user\'s questions.';
    }

    /**
     * Get a description of this agent.
     */
    public function description(): ?string
    {
        return 'A general-purpose assistant for interactive chat.';
    }
}
