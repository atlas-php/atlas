<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Support;

use Prism\Prism\Moderation\Response as PrismResponse;

/**
 * Atlas response wrapper for moderation results.
 *
 * Contains the moderation results for all inputs along with metadata
 * from the API response.
 */
final readonly class ModerationResponse
{
    /**
     * @param  array<int, ModerationResult>  $results  The moderation results for each input.
     * @param  string  $id  The API response ID.
     * @param  string  $model  The model used for moderation.
     */
    public function __construct(
        public array $results,
        public string $id,
        public string $model,
    ) {}

    /**
     * Check if any of the results are flagged.
     */
    public function isFlagged(): bool
    {
        foreach ($this->results as $result) {
            if ($result->flagged) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the first flagged result, if any.
     */
    public function firstFlagged(): ?ModerationResult
    {
        foreach ($this->results as $result) {
            if ($result->flagged) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Get all flagged results.
     *
     * @return array<int, ModerationResult>
     */
    public function flagged(): array
    {
        $flagged = [];

        foreach ($this->results as $result) {
            if ($result->flagged) {
                $flagged[] = $result;
            }
        }

        return $flagged;
    }

    /**
     * Get aggregated categories from all results.
     *
     * Returns true for a category if any result flagged it.
     *
     * @return array<string, bool>
     */
    public function categories(): array
    {
        $categories = [];

        foreach ($this->results as $result) {
            foreach ($result->categories as $category => $isFlagged) {
                if (! isset($categories[$category])) {
                    $categories[$category] = false;
                }
                if ($isFlagged) {
                    $categories[$category] = true;
                }
            }
        }

        return $categories;
    }

    /**
     * Get aggregated category scores from all results.
     *
     * Returns the maximum score for each category across all results.
     *
     * @return array<string, float>
     */
    public function categoryScores(): array
    {
        $scores = [];

        foreach ($this->results as $result) {
            foreach ($result->categoryScores as $category => $score) {
                if (! isset($scores[$category]) || $score > $scores[$category]) {
                    $scores[$category] = $score;
                }
            }
        }

        return $scores;
    }

    /**
     * Create from a Prism Response.
     */
    public static function fromPrismResponse(PrismResponse $response): self
    {
        $results = [];

        foreach ($response->results as $prismResult) {
            $results[] = ModerationResult::fromPrismResult($prismResult);
        }

        return new self(
            results: $results,
            id: $response->meta->id,
            model: $response->meta->model,
        );
    }
}
