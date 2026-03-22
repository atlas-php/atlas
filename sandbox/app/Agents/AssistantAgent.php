<?php

declare(strict_types=1);

namespace App\Agents;

use App\Tools\GenerateImageTool;
use App\Tools\GenerateSpeechTool;
use App\Tools\GenerateVideoTool;
use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;
use Atlasphp\Atlas\Persistence\Concerns\HasMemory;
use Atlasphp\Atlas\Persistence\Memory\MemoryConfig;

/**
 * Multi-modal assistant agent with image/video generation and memory.
 *
 * Demonstrates the full Atlas agent pipeline: persistence, conversation
 * threads, memory tools, and multi-modal tool calling.
 *
 * Provider and model are inherited from config('atlas.defaults.text').
 */
class AssistantAgent extends Agent
{
    use HasConversations;
    use HasMemory;

    public function key(): string
    {
        return 'assistant';
    }

    public function instructions(): string
    {
        return <<<'PROMPT'
        You are a helpful multi-modal assistant. Today is {DATE}.

        ## Tools
        You have access to image generation, video generation, and text-to-speech tools.
        When the user asks for visual or audio content, use the appropriate tool.

        When you generate an image, display the result using markdown: ![description](url).

        ## Memory
        You have memory tools. Proactively save important facts, preferences,
        and context the user shares. Before answering questions that might
        rely on past context, search your memory first.

        {MEMORIES}
        PROMPT;
    }

    /**
     * @return array<int, class-string>
     */
    public function tools(): array
    {
        return [
            GenerateImageTool::class,
            GenerateVideoTool::class,
            GenerateSpeechTool::class,
        ];
    }

    public function memory(): MemoryConfig
    {
        return MemoryConfig::make()
            ->withTools()
            ->variables(['memories']);
    }

    public function maxSteps(): ?int
    {
        return 10;
    }

    public function parallelToolCalls(): bool
    {
        return false;
    }

    public function temperature(): ?float
    {
        return 0.7;
    }
}
