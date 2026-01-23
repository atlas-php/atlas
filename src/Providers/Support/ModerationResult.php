<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Support;

use Prism\Prism\ValueObjects\ModerationResult as PrismModerationResult;

/**
 * Represents a single moderation result for an input.
 *
 * Contains the flagged status, categories, and category scores for a single
 * piece of content that was moderated.
 */
final readonly class ModerationResult
{
    /**
     * @param  bool  $flagged  Whether the content was flagged.
     * @param  array<string, bool>  $categories  Category flags (e.g., ['violence' => true, 'hate' => false]).
     * @param  array<string, float>  $categoryScores  Category scores (e.g., ['violence' => 0.95, 'hate' => 0.01]).
     */
    public function __construct(
        public bool $flagged,
        public array $categories,
        public array $categoryScores,
    ) {}

    /**
     * Get all categories that were flagged.
     *
     * @return array<string>
     */
    public function flaggedCategories(): array
    {
        $flagged = [];

        foreach ($this->categories as $category => $isFlagged) {
            if ($isFlagged) {
                $flagged[] = $category;
            }
        }

        return $flagged;
    }

    /**
     * Check if a specific category was flagged.
     */
    public function isCategoryFlagged(string $category): bool
    {
        return $this->categories[$category] ?? false;
    }

    /**
     * Get the score for a specific category.
     */
    public function getCategoryScore(string $category): ?float
    {
        return $this->categoryScores[$category] ?? null;
    }

    /**
     * Create from a Prism ModerationResult.
     */
    public static function fromPrismResult(PrismModerationResult $result): self
    {
        return new self(
            flagged: $result->flagged,
            categories: $result->categories,
            categoryScores: $result->categoryScores,
        );
    }
}
