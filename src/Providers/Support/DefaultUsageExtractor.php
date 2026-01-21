<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Support;

use Atlasphp\Atlas\Providers\Contracts\UsageExtractorContract;

/**
 * Default usage extractor for providers without specific extractors.
 *
 * Returns a standard usage array structure for any provider.
 */
class DefaultUsageExtractor implements UsageExtractorContract
{
    /**
     * Extract usage data from a provider response.
     *
     * @param  mixed  $response  The provider response.
     * @return array<string, mixed> Normalized usage data.
     */
    public function extract(mixed $response): array
    {
        if (is_array($response) && isset($response['usage'])) {
            return [
                'prompt_tokens' => $response['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $response['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $response['usage']['total_tokens'] ?? 0,
            ];
        }

        if (is_object($response) && property_exists($response, 'usage')) {
            $usage = $response->usage;

            return [
                'prompt_tokens' => $usage->promptTokens ?? $usage->prompt_tokens ?? 0,
                'completion_tokens' => $usage->completionTokens ?? $usage->completion_tokens ?? 0,
                'total_tokens' => $usage->totalTokens ?? $usage->total_tokens ?? 0,
            ];
        }

        return [
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
        ];
    }

    /**
     * Get the provider name this extractor handles.
     *
     * @return string The provider identifier.
     */
    public function provider(): string
    {
        return 'default';
    }
}
