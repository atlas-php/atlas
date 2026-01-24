<?php

declare(strict_types=1);

namespace App\Agents;

use Atlasphp\Atlas\Agents\AgentDefinition;

/**
 * Agent for local LLM servers (LM Studio, Ollama with OpenAI compat, etc.).
 *
 * Uses the OpenAI provider with a custom URL pointing to the local server.
 * Configure OLLAMA_URL and OLLAMA_MODEL in your .env file.
 */
class LocalLMAgent extends AgentDefinition
{
    /**
     * Get the AI provider for this agent.
     *
     * Uses OpenAI provider since LM Studio and similar servers
     * provide an OpenAI-compatible API.
     */
    public function provider(): ?string
    {
        return 'openai';
    }

    /**
     * Get the model to use for this agent.
     *
     * Reads from OLLAMA_MODEL environment variable.
     */
    public function model(): ?string
    {
        return env('OLLAMA_MODEL', 'llama3');
    }

    public function systemPrompt(): ?string
    {
        // optional system prompt
        // LM studio in this test has its own system prompt
        return null;
    }

    /**
     * Get a description of this agent.
     */
    public function description(): ?string
    {
        return 'A local LLM assistant using OpenAI-compatible API.';
    }
}
