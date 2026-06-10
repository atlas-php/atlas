<?php

declare(strict_types=1);

namespace App\Agents;

use App\Tools\CurrentDateTimeTool;
use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;
use Atlasphp\Atlas\Providers\Tools\ProviderTool;
use Atlasphp\Atlas\Providers\Tools\WebSearch;

/**
 * General-purpose text assistant backed by OpenAI.
 *
 * The everyday default: answers questions, writes, and uses web search
 * for current events. Demonstrates a plain text agent with a provider tool.
 */
class AtlasAgent extends Agent
{
    use HasConversations;

    public function key(): string
    {
        return 'atlas';
    }

    public function name(): string
    {
        return 'Atlas';
    }

    public function description(): ?string
    {
        return 'General assistant for everyday questions, writing, and live web search.';
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
        You are {NAME}, a helpful, friendly assistant in a text chat. Today is {DATE}.

        Keep answers clear and well-formatted with Markdown.
        When the user asks about current events or anything time-sensitive, use web search.
        PROMPT;
    }

    /**
     * @return array<int, class-string>
     */
    public function tools(): array
    {
        return [
            CurrentDateTimeTool::class,
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
        return 4;
    }

    public function temperature(): ?float
    {
        return 0.7;
    }
}
