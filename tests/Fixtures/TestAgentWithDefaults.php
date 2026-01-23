<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tests\Fixtures;

use Atlasphp\Atlas\Agents\AgentDefinition;

/**
 * Test agent fixture that uses config defaults for provider and model.
 *
 * Used to verify that agents can omit provider/model
 * and the executor falls back to config defaults.
 */
class TestAgentWithDefaults extends AgentDefinition
{
    public function key(): string
    {
        return 'test-agent-with-defaults';
    }

    public function name(): string
    {
        return 'Test Agent With Defaults';
    }

    public function provider(): ?string
    {
        return null; // Uses config default
    }

    public function model(): ?string
    {
        return null; // Uses config default
    }

    public function systemPrompt(): ?string
    {
        return null; // No system prompt
    }

    public function description(): ?string
    {
        return 'A test agent that uses config defaults.';
    }

    public function maxSteps(): ?int
    {
        return 5;
    }
}
