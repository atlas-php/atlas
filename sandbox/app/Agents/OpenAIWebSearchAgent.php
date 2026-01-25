<?php

declare(strict_types=1);

namespace App\Agents;

use Atlasphp\Atlas\Agents\AgentDefinition;

/**
 * OpenAI agent with web search provider tool.
 */
class OpenAIWebSearchAgent extends AgentDefinition
{
    public function provider(): ?string
    {
        return 'openai';
    }

    public function model(): ?string
    {
        return 'gpt-4o';
    }

    public function systemPrompt(): ?string
    {
        return 'You are a helpful assistant with access to web search. '
            .'Use web search to find current information when asked.';
    }

    /**
     * Use simplified provider tool format - just a string.
     *
     * @return array<int, string>
     */
    public function providerTools(): array
    {
        return ['web_search_preview'];
    }

    public function maxSteps(): ?int
    {
        return 5;
    }
}
