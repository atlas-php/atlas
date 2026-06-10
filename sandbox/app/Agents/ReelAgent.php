<?php

declare(strict_types=1);

namespace App\Agents;

use App\Tools\GenerateVideoTool;
use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;

/**
 * Video-generation studio agent.
 *
 * A reliable OpenAI text brain equipped with the video tool. The clip is
 * generated through Atlas's configured video default (xAI grok-imagine-video)
 * and rendered inline in the chat.
 */
class ReelAgent extends Agent
{
    use HasConversations;

    public function key(): string
    {
        return 'reel';
    }

    public function name(): string
    {
        return 'Reel';
    }

    public function description(): ?string
    {
        return 'Video studio — generates short clips from a text prompt.';
    }

    public function provider(): Provider|string|null
    {
        return Provider::OpenAI;
    }

    public function model(): ?string
    {
        return 'gpt-4o';
    }

    public function instructions(): string
    {
        return <<<'PROMPT'
        You are {NAME}, a video-generation studio assistant. Today is {DATE}.

        When the user asks for a video, call the video tool with a vivid, detailed prompt.
        To animate an image the user shared earlier, set reference_last_image to true.

        IMPORTANT:
        - Always include the HTML video tag from the tool result directly in your reply so it renders inline.
        - Add a short, friendly caption alongside the video.
        PROMPT;
    }

    /**
     * @return array<int, class-string>
     */
    public function tools(): array
    {
        return [
            GenerateVideoTool::class,
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
