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
use Atlasphp\Atlas\Providers\Tools\ProviderTool;
use Atlasphp\Atlas\Providers\Tools\WebSearch;

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
        You have access to image generation, video generation, text-to-speech, and web search tools.
        When the user asks for visual or audio content, use the appropriate tool.
        When the user asks about current events or needs up-to-date information, use web search.

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

    /**
     * @return array<int, ProviderTool>
     */
    public function providerTools(): array
    {
        return [
            new WebSearch,
        ];
    }

    public function temperature(): ?float
    {
        return 0.7;
    }
}
