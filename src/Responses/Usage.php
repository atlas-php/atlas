<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses;

/**
 * Token usage information from a provider response.
 */
final class Usage
{
    public function __construct(
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly ?int $reasoningTokens = null,
        public readonly ?int $cachedTokens = null,
    ) {}

    /**
     * Get the total number of tokens used.
     */
    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    /**
     * Merge this usage with another, summing all token counts.
     */
    public function merge(Usage $other): static
    {
        $reasoningTokens = ($this->reasoningTokens ?? 0) + ($other->reasoningTokens ?? 0);
        $cachedTokens = ($this->cachedTokens ?? 0) + ($other->cachedTokens ?? 0);

        return new self(
            inputTokens: $this->inputTokens + $other->inputTokens,
            outputTokens: $this->outputTokens + $other->outputTokens,
            reasoningTokens: ($this->reasoningTokens !== null || $other->reasoningTokens !== null) ? $reasoningTokens : null,
            cachedTokens: ($this->cachedTokens !== null || $other->cachedTokens !== null) ? $cachedTokens : null,
        );
    }
}
