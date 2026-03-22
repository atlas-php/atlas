<?php

declare(strict_types=1);

namespace App\Agents;

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;
use Atlasphp\Atlas\Persistence\Concerns\HasMemory;
use Atlasphp\Atlas\Persistence\Memory\MemoryConfig;

/**
 * Minimal agent for testing memory tools in isolation.
 *
 * No media tools, no web search — just memory tools and simple conversation.
 */
class MemoryTestAgent extends Agent
{
    use HasConversations;
    use HasMemory;

    public function key(): string
    {
        return 'memory-test';
    }

    public function instructions(): string
    {
        return <<<'PROMPT'
        You are a helpful assistant with memory capabilities.
        When asked to remember something, use the remember_memory tool immediately.
        When asked to recall something, use the recall_memory or search_memory tool.
        Always confirm what you remembered or recalled.
        PROMPT;
    }

    public function memory(): MemoryConfig
    {
        return MemoryConfig::make()
            ->withTools();
    }

    public function maxSteps(): ?int
    {
        return 10;
    }

    public function temperature(): ?float
    {
        return 0.0;
    }
}
