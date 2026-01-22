<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tests\Fixtures;

use Atlasphp\Atlas\Agents\AgentDefinition;
use Prism\Prism\ValueObjects\ProviderTool;

/**
 * Test agent fixture with ProviderTool instances for unit tests.
 */
class TestAgentWithProviderToolInstances extends AgentDefinition
{
    public function key(): string
    {
        return 'test-agent-with-provider-tool-instances';
    }

    public function name(): string
    {
        return 'Test Agent With Provider Tool Instances';
    }

    public function provider(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return 'gpt-4o';
    }

    public function systemPrompt(): string
    {
        return 'You are a helpful assistant.';
    }

    /**
     * Return provider tools including ProviderTool instances directly.
     *
     * @return array<int, string|array<string, mixed>|ProviderTool>
     */
    public function providerTools(): array
    {
        return [
            new ProviderTool(type: 'web_search', name: 'custom_search', options: ['max_results' => 10]),
            'code_execution',
        ];
    }

    public function maxSteps(): ?int
    {
        return 5;
    }
}
