<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Requests;

use Atlasphp\Atlas\Enums\ReasoningEffort;

/**
 * Immutable reasoning/thinking configuration for a text request.
 *
 * Carries a normalized effort level plus an optional explicit token budget that
 * overrides the effort-derived default on budget-based providers. Each provider's
 * text handler reads the field it understands (effort string or budget tokens).
 */
final class Reasoning
{
    public function __construct(
        public readonly ReasoningEffort $effort,
        public readonly ?int $budgetTokens = null,
        public readonly bool $includeSummary = false,
    ) {}

    /**
     * Thinking-token budget for budget-based providers: the explicit override
     * when set, otherwise the effort-derived default.
     */
    public function budgetTokens(): int
    {
        return $this->budgetTokens ?? $this->effort->toBudgetTokens();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'effort' => $this->effort->value,
            'budget_tokens' => $this->budgetTokens,
            'include_summary' => $this->includeSummary,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            effort: ReasoningEffort::from($data['effort']),
            budgetTokens: $data['budget_tokens'] ?? null,
            includeSummary: (bool) ($data['include_summary'] ?? false),
        );
    }
}
