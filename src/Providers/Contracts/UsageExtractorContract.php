<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Contracts;

/**
 * Contract for extracting usage data from provider responses.
 *
 * Different providers return usage information in different formats.
 * Implementations normalize this data to a common structure.
 */
interface UsageExtractorContract
{
    /**
     * Extract usage data from a provider response.
     *
     * @param  mixed  $response  The provider response.
     * @return array<string, mixed> Normalized usage data.
     */
    public function extract(mixed $response): array;

    /**
     * Get the provider name this extractor handles.
     *
     * @return string The provider identifier.
     */
    public function provider(): string;
}
