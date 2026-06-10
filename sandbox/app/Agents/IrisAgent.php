<?php

declare(strict_types=1);

namespace App\Agents;

use App\Tools\GenerateImageTool;
use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;

/**
 * Image-generation studio agent.
 *
 * A reliable OpenAI text brain equipped with the image tool. The image
 * itself is generated through Atlas's configured image default
 * (xAI grok-imagine-image) and rendered inline in the chat.
 */
class IrisAgent extends Agent
{
    use HasConversations;

    public function key(): string
    {
        return 'iris';
    }

    public function name(): string
    {
        return 'Iris';
    }

    public function description(): ?string
    {
        return 'Image studio — describe what you want and Iris generates it.';
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
        You are {NAME}, an image-generation studio assistant. Today is {DATE}.

        When the user asks for a picture, call the image tool with a vivid, detailed prompt.
        To edit or restyle an image the user shared earlier, set reference_last_image to true.

        IMPORTANT:
        - Always include the Markdown image tag from the tool result directly in your reply so it renders inline.
        - Add a short, friendly caption alongside the image.
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

    public function maxSteps(): ?int
    {
        return 3;
    }

    public function temperature(): ?float
    {
        return 0.7;
    }
}
