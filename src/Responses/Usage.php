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
        public readonly ?int $cacheWriteTokens = null,
    ) {}

    /**
     * Get the total number of tokens used.
     */
    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    /**
     * Convert to an array for JSON persistence, omitting null fields.
     *
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return array_filter([
            'inputTokens' => $this->inputTokens,
            'outputTokens' => $this->outputTokens,
            'reasoningTokens' => $this->reasoningTokens,
            'cachedTokens' => $this->cachedTokens,
            'cacheWriteTokens' => $this->cacheWriteTokens,
        ], fn ($v) => $v !== null);
    }

    /**
     * Create a Usage instance from a persisted JSON array.
     *
     * @param  array<string, int>|null  $data
     */
    public static function fromArray(?array $data): self
    {
        if ($data === null) {
            return new self(0, 0);
        }

        return new self(
            inputTokens: $data['inputTokens'] ?? 0,
            outputTokens: $data['outputTokens'] ?? 0,
            reasoningTokens: $data['reasoningTokens'] ?? null,
            cachedTokens: $data['cachedTokens'] ?? null,
            cacheWriteTokens: $data['cacheWriteTokens'] ?? null,
        );
    }

    /**
     * Merge this usage with another, summing all token counts.
     */
    public function merge(Usage $other): static
    {
        $reasoningTokens = ($this->reasoningTokens ?? 0) + ($other->reasoningTokens ?? 0);
        $cachedTokens = ($this->cachedTokens ?? 0) + ($other->cachedTokens ?? 0);
        $cacheWriteTokens = ($this->cacheWriteTokens ?? 0) + ($other->cacheWriteTokens ?? 0);

        return new self(
            inputTokens: $this->inputTokens + $other->inputTokens,
            outputTokens: $this->outputTokens + $other->outputTokens,
            reasoningTokens: ($this->reasoningTokens !== null || $other->reasoningTokens !== null) ? $reasoningTokens : null,
            cachedTokens: ($this->cachedTokens !== null || $other->cachedTokens !== null) ? $cachedTokens : null,
            cacheWriteTokens: ($this->cacheWriteTokens !== null || $other->cacheWriteTokens !== null) ? $cacheWriteTokens : null,
        );
    }
}
