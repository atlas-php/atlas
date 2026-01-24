<?php

declare(strict_types=1);

namespace App\Agents;

use Atlasphp\Atlas\Agents\AgentDefinition;

/**
 * OpenAI vision agent for testing multimodal attachments.
 *
 * Uses GPT-4o which has vision capabilities for analyzing images.
 */
class OpenAIVisionAgent extends AgentDefinition
{
    /**
     * Get the unique key for this agent.
     */
    public function key(): string
    {
        return 'openai-vision';
    }

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
        return 'You are a vision assistant that analyzes images and documents. '
            .'Describe what you see in detail, including colors, objects, text, and any other relevant features. '
            .'Be precise and thorough in your analysis.';
    }

    /**
     * Get a description of this agent.
     */
    public function description(): ?string
    {
        return 'Vision agent using OpenAI GPT-4o for image and document analysis.';
    }
}
