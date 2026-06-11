<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Concerns;

use Atlasphp\Atlas\Responses\TokenCount;
use Atlasphp\Atlas\Support\TokenCounter;

/**
 * Heuristic token estimation for providers without a native count endpoint.
 *
 * Walks a built request payload and sums the chars/4 estimate over every
 * string leaf. Used by OpenAI-compatible and local providers (xAI, Ollama,
 * LM Studio) and as a fallback when a native endpoint is unavailable. The
 * resulting TokenCount is always flagged estimated:true — it is approximate,
 * and base64-encoded media inflates the figure since the heuristic cannot
 * distinguish encoded bytes from prose.
 */
trait CountsTokens
{
    /**
     * Build an estimated TokenCount from a request payload.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function estimateTokens(string $provider, string $model, array $payload): TokenCount
    {
        return new TokenCount(
            inputTokens: $this->sumPayloadTokens($payload),
            estimated: true,
            provider: $provider,
            model: $model,
        );
    }

    /**
     * Recursively sum the heuristic token count over a payload's string leaves.
     *
     * @param  array<int|string, mixed>  $payload
     */
    private function sumPayloadTokens(array $payload): int
    {
        $total = 0;

        foreach ($payload as $value) {
            if (is_array($value)) {
                $total += $this->sumPayloadTokens($value);
            } elseif (is_string($value)) {
                $total += TokenCounter::count($value);
            }
        }

        return $total;
    }
}
