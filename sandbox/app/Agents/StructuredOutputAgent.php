<?php

declare(strict_types=1);

namespace App\Agents;

use Atlasphp\Atlas\Agents\AgentDefinition;
use Atlasphp\Atlas\Schema\Schema;
use Prism\Prism\Contracts\Schema as PrismSchema;

/**
 * Agent for testing structured output extraction.
 *
 * Demonstrates both:
 * 1. Agent-level schema via schema() - default person extraction
 * 2. On-demand schema via withSchema() - overrides agent schema
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

    /**
     * Get the schema for structured output.
     *
     * Returns a default "person" schema. Can be overridden at call time via withSchema().
     */
    public function schema(): ?PrismSchema
    {
        return Schema::object('person', 'Information about a person')
            ->string('name', 'The person\'s full name')
            ->number('age', 'The person\'s age in years')
            ->string('email', 'The person\'s email address')
            ->string('occupation', 'The person\'s job or occupation')
            ->build();
    }
}
