<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tests\Fixtures;

use Atlasphp\Atlas\Agents\AgentDefinition;

/**
 * Test agent fixture with invalid provider tools for unit tests.
 */
class TestAgentWithInvalidProviderTools extends AgentDefinition
{
    public function key(): string
    {
        return 'test-agent-with-invalid-provider-tools';
    }

    public function name(): string
    {
        return 'Test Agent With Invalid Provider Tools';
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
        return 'You are a helpful assistant.';
    }

    /**
     * Return provider tools with invalid format (array without 'type' key).
     *
     * @return array<int, string|array<string, mixed>>
     */
    public function providerTools(): array
    {
        return [
            ['invalid_key' => 'value', 'another_key' => 'data'],
        ];
    }

    public function maxSteps(): ?int
    {
        return 5;
    }
}
