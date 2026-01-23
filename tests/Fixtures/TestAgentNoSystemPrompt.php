<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tests\Fixtures;

use Atlasphp\Atlas\Agents\AgentDefinition;

/**
 * Test agent fixture without a system prompt.
 *
 * Used to verify that agents can omit system prompts
 * and the executor handles null system prompts correctly.
 */
class TestAgentNoSystemPrompt extends AgentDefinition
{
    public function key(): string
    {
        return 'test-agent-no-system-prompt';
    }

    public function name(): string
    {
        return 'Test Agent No System Prompt';
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
        return null;
    }

    public function description(): ?string
    {
        return 'A test agent without a system prompt.';
    }

    public function maxSteps(): ?int
    {
        return 5;
    }
}
