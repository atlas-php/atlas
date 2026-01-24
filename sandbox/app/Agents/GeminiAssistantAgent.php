<?php

declare(strict_types=1);

namespace App\Agents;

use Atlasphp\Atlas\Agents\AgentDefinition;

/**
 * Gemini-based assistant agent for testing.
 *
 * Tests Google Gemini models.
 */
class GeminiAssistantAgent extends AgentDefinition
{
    /**
     * Get the AI provider for this agent.
     */
    public function provider(): ?string
    {
        return 'gemini';
    }

    /**
     * Get the model to use for this agent.
     */
    public function model(): ?string
    {
        return 'gemini-2.0-flash';
    }

    /**
     * Get the system prompt for this agent.
     */
    public function systemPrompt(): ?string
    {
        return 'You are a helpful assistant powered by Google Gemini. '
            .'Be concise and helpful in your responses.';
    }

    /**
     * Get a description of this agent.
     */
    public function description(): ?string
    {
        return 'An assistant powered by Google Gemini for testing.';
    }
}
