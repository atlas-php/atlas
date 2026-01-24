<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tests\Fixtures;

use Atlasphp\Atlas\Agents\AgentDefinition;

/**
 * Test agent fixture with clientOptions, providerOptions, and providerTools.
 *
 * Used to verify that these agent-level options are applied to Prism requests.
 */
class TestAgentWithOptions extends AgentDefinition
{
    public function key(): string
    {
        return 'test-agent-with-options';
    }

    public function name(): string
    {
        return 'Test Agent With Options';
    }

    public function provider(): ?string
    {
        return 'openai';
    }

    public function model(): ?string
    {
        return 'gpt-4';
    }

    public function systemPrompt(): ?string
    {
        return 'You are a helpful assistant.';
    }

    public function description(): ?string
    {
        return 'A test agent with client options, provider options, and provider tools.';
    }

    /**
     * Client options for HTTP configuration.
     *
     * @return array<string, mixed>
     */
    public function clientOptions(): array
    {
        return [
            'timeout' => 60,
            'connect_timeout' => 10,
        ];
    }

    /**
     * Provider-specific options.
     *
     * @return array<string, mixed>
     */
    public function providerOptions(): array
    {
        return [
            'presence_penalty' => 0.5,
            'frequency_penalty' => 0.3,
        ];
    }

    /**
     * Provider tools (like web_search, code_execution).
     *
     * @return array<int, string|array<string, mixed>>
     */
    public function providerTools(): array
    {
        return [
            'web_search',
            ['type' => 'code_execution', 'name' => 'execute_code'],
        ];
    }
}
