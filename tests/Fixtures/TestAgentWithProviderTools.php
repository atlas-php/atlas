<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tests\Fixtures;

use Atlasphp\Atlas\Agents\AgentDefinition;

/**
 * Test agent fixture with provider tools for unit tests.
 */
class TestAgentWithProviderTools extends AgentDefinition
{
    public function key(): string
    {
        return 'test-agent-with-provider-tools';
    }

    public function name(): string
    {
        return 'Test Agent With Provider Tools';
    }

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
        return 'You are a helpful assistant with web search.';
    }

    /**
     * Return provider tools in simplified format.
     *
     * @return array<int, string|array{type: string, ...}>
     */
    public function providerTools(): array
    {
        return [
            'web_search_preview',
            ['type' => 'code_execution', 'container' => 'python'],
        ];
    }

    public function maxSteps(): ?int
    {
        return 5;
    }
}
