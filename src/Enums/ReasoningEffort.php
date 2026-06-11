<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Enums;

/**
 * Normalized reasoning/thinking effort levels.
 *
 * Providers express reasoning two ways: effort levels (OpenAI/xAI) and token
 * budgets (Anthropic/Gemini). Atlas exposes a single effort knob and each text
 * handler maps it to the provider's native shape — effort-based providers use
 * the string value, budget-based providers use {@see self::toBudgetTokens()}.
 */
enum ReasoningEffort: string
{
    case Minimal = 'minimal';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    /**
     * Default thinking-token budget for budget-based providers (Anthropic, Gemini)
     * when no explicit budget is supplied. Anthropic's minimum is 1024 tokens.
     */
    public function toBudgetTokens(): int
    {
        return match ($this) {
            self::Minimal => 1024,
            self::Low => 4096,
            self::Medium => 8192,
            self::High => 16000,
        };
    }
}
