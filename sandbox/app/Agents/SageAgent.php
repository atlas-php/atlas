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
 * Reasoning-focused text agent backed by xAI Grok.
 *
 * Tuned for complex, multi-step problems. Demonstrates a second provider
 * (xAI) plus live web search via Grok's search tool.
 */
class SageAgent extends Agent
{
    use HasConversations;

    public function key(): string
    {
        return 'sage';
    }

    public function name(): string
    {
        return 'Sage';
    }

    public function description(): ?string
    {
        return 'Reasoning specialist for complex, multi-step problems (xAI Grok).';
    }

    public function provider(): Provider|string|null
    {
        return Provider::xAI;
    }

    public function model(): ?string
    {
        return 'grok-4';
    }

    public function instructions(): string
    {
        return <<<'PROMPT'
        You are {NAME}, a careful, analytical assistant in a text chat. Today is {DATE}.

        Think step by step through hard problems and show your reasoning concisely.
        Format answers with Markdown. Use web search for current events or facts you are unsure of.
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
        return 0.6;
    }
}
