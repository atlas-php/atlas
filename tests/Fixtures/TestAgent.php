<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tests\Fixtures;

use Atlasphp\Atlas\Agents\AgentDefinition;

/**
 * Test agent fixture for unit and feature tests.
 */
class TestAgent extends AgentDefinition
{
    public function key(): string
    {
        return 'test-agent';
    }

    public function name(): string
    {
        return 'Test Agent';
    }

    public function provider(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return 'gpt-4';
    }

    public function systemPrompt(): string
    {
        return 'You are {agent_name}. Help {user_name} with their request.';
    }

    public function description(): ?string
    {
        return 'A test agent for unit tests.';
    }

    public function tools(): array
    {
        return [TestTool::class];
    }

    public function temperature(): ?float
    {
        return 0.7;
    }

    public function maxTokens(): ?int
    {
        return 1000;
    }

    public function maxSteps(): ?int
    {
        return 5;
    }
}
