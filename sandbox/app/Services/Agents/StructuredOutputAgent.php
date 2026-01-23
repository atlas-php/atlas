<?php

declare(strict_types=1);

namespace App\Services\Agents;

use Atlasphp\Atlas\Agents\AgentDefinition;

/**
 * Agent for testing structured output extraction.
 *
 * Designed to extract structured data from text following a provided schema.
 */
class StructuredOutputAgent extends AgentDefinition
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
        return 'Extract structured data from the input. '
            .'Be precise and follow the provided schema exactly. '
            .'Only extract information that is explicitly stated in the input. '
            .'Do not infer or add information not present in the source text.';
    }

    /**
     * Get a description of this agent.
     */
    public function description(): ?string
    {
        return 'An agent for extracting structured data from unstructured text.';
    }
}
