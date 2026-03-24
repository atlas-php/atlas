<?php

declare(strict_types=1);

namespace App\Agents;

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;
use Atlasphp\Atlas\Persistence\Memory\Tools\MemoryRecall;
use Atlasphp\Atlas\Persistence\Memory\Tools\MemorySearch;
use Atlasphp\Atlas\Persistence\Memory\Tools\RememberMemory;

/**
 * Minimal agent for testing memory tools in isolation.
 *
 * No media tools, no web search — just memory tools and simple conversation.
 */
class MemoryTestAgent extends Agent
{
    use HasConversations;

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

    /**
     * @return array<int, class-string>
     */
    public function tools(): array
    {
        return [
            RememberMemory::class,
            MemoryRecall::class,
            MemorySearch::class,
        ];
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
