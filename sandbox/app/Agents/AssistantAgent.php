<?php

declare(strict_types=1);

namespace App\Agents;

use App\Tools\GenerateImageTool;
use App\Tools\GenerateSpeechTool;
use App\Tools\GenerateVideoTool;
use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;
use Atlasphp\Atlas\Persistence\Memory\Tools\MemoryRecall;
use Atlasphp\Atlas\Persistence\Memory\Tools\MemorySearch;
use Atlasphp\Atlas\Persistence\Memory\Tools\RememberMemory;
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

    public function key(): string
    {
        return 'sarah-text';
    }

    public function name(): string
    {
        return 'Sarah';
    }

    public function instructions(): string
    {
        return <<<'PROMPT'
        You are {NAME}, a helpful assistant in text chat. Today is {DATE}.

        ## Tools
        You have access to image generation, video generation, text-to-speech, and web search tools.

        IMPORTANT RULES:
        - When you generate an image, include the markdown image tag from the tool result directly in your response.
        - When you generate audio, include the HTML audio tag from the tool result directly in your response.
        - When you generate video, include the HTML video tag from the tool result directly in your response.
        - When the user asks about current events, use web search.

        ## Memory
        You have memory tools. Save important facts and preferences the user shares.
        Before answering questions that might rely on past context, search your memory.
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
            RememberMemory::class,
            MemoryRecall::class,
            MemorySearch::class,
        ];
    }

    public function maxSteps(): ?int
    {
        return 4;
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
