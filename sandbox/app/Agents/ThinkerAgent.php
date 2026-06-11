<?php

declare(strict_types=1);

namespace App\Agents;

use App\Tools\CurrentDateTimeTool;
use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Enums\ReasoningEffort;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;
use Atlasphp\Atlas\Requests\Reasoning;

/**
 * Extended-thinking demo agent backed by Anthropic.
 *
 * Reasoning is enabled, so the model streams its thinking live (rendered in the
 * UI's collapsible "Thinking" block) and the signed thinking is persisted on the
 * execution steps for later audit.
 */
class ThinkerAgent extends Agent
{
    use HasConversations;

    public function key(): string
    {
        return 'thinker';
    }

    public function name(): string
    {
        return 'Thinker';
    }

    public function description(): ?string
    {
        return 'Shows its work — streams extended thinking before answering (Anthropic).';
    }

    public function provider(): Provider|string|null
    {
        return Provider::Anthropic;
    }

    public function model(): ?string
    {
        return 'claude-sonnet-4-5-20250929';
    }

    public function reasoning(): ?Reasoning
    {
        return new Reasoning(ReasoningEffort::Medium);
    }

    /**
     * A tool so the run goes through the executor — its steps persist the
     * reasoning for the UI's collapsible "Thinking" trace.
     *
     * @return array<int, class-string>
     */
    public function tools(): array
    {
        return [CurrentDateTimeTool::class];
    }

    public function instructions(): string
    {
        return <<<'PROMPT'
        You are Thinker, a careful analytical assistant in a text chat.

        Reason through the problem step by step in your thinking, then give a clear,
        well-formatted Markdown answer. Be concise in the final answer — the detailed
        reasoning belongs in your thinking, not the reply.
        PROMPT;
    }
}
