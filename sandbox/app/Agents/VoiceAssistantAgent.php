<?php

declare(strict_types=1);

namespace App\Agents;

use App\Tools\GenerateImageTool;
use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;
use Atlasphp\Atlas\Providers\Tools\ProviderTool;
use Atlasphp\Atlas\Providers\Tools\WebSearch;

/**
 * Voice-optimized assistant agent using xAI's realtime model.
 *
 * Uses the same conversation thread as sarah-text so the voice agent
 * has full context of prior text messages when switching modalities.
 */
class VoiceAssistantAgent extends Agent
{
    use HasConversations;

    public function key(): string
    {
        return 'sarah-voice';
    }

    public function name(): string
    {
        return 'Sarah';
    }

    public function provider(): Provider|string|null
    {
        return Provider::xAI;
    }

    public function model(): ?string
    {
        return 'grok-3-fast-realtime';
    }

    public function voice(): ?string
    {
        return 'ara';
    }

    public function instructions(): string
    {
        return <<<'PROMPT'
        You are {NAME}, a helpful assistant in voice chat. Today is {DATE}.

        Keep your responses concise and conversational — you're in a voice call, not a text chat.
        Speak naturally in conversation.

        You have access to image generation and web search tools.
        When the user asks about current events, use web search.

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
        ];
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

    public function maxSteps(): ?int
    {
        return 3;
    }

    public function temperature(): ?float
    {
        return 0.7;
    }
}
