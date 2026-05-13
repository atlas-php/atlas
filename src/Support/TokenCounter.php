<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Support;

/**
 * Approximate token counter for embedding budgets.
 *
 * Uses the well-known chars/4 heuristic: tracks within a few percent of
 * the real BPE count on English prose for OpenAI-family models, and is
 * dependency-free, deterministic, and fast. Good enough for chunk sizing
 * where exact accuracy is not required — chunk boundaries are guided by
 * structure, not token count.
 */
final class TokenCounter
{
    public static function count(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        return (int) ceil(mb_strlen($text) / 4);
    }
}
