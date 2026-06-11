<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses;

/**
 * Pre-flight input-token count for a text request.
 *
 * Returned by countTokens(). Reflects the input tokens of the exact payload
 * Atlas would send (messages, system, tools, media). The $estimated flag is
 * false when the number came from a provider's native count endpoint and true
 * when it was approximated with the chars/4 heuristic (providers without a
 * native endpoint). Output tokens are not counted — they are unknown until the
 * model responds.
 */
final class TokenCount
{
    /**
     * @param  array<string, int>  $breakdown  Optional per-category detail
     *                                         (e.g. ['cached_tokens' => 1200]).
     */
    public function __construct(
        public readonly int $inputTokens,
        public readonly bool $estimated,
        public readonly string $provider,
        public readonly string $model,
        public readonly array $breakdown = [],
    ) {}

    /**
     * Convert to an array for JSON persistence or logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'input_tokens' => $this->inputTokens,
            'estimated' => $this->estimated,
            'provider' => $this->provider,
            'model' => $this->model,
        ];

        if ($this->breakdown !== []) {
            $data['breakdown'] = $this->breakdown;
        }

        return $data;
    }
}
