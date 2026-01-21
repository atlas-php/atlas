<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Services;

use Atlasphp\Atlas\Providers\Contracts\UsageExtractorContract;
use Atlasphp\Atlas\Providers\Support\DefaultUsageExtractor;

/**
 * Registry for provider-specific usage extractors.
 *
 * Manages extractors that normalize usage data from different providers.
 * Falls back to a default extractor if no provider-specific one is registered.
 */
class UsageExtractorRegistry
{
    /**
     * Registered extractors keyed by provider name.
     *
     * @var array<string, UsageExtractorContract>
     */
    protected array $extractors = [];

    protected DefaultUsageExtractor $defaultExtractor;

    public function __construct()
    {
        $this->defaultExtractor = new DefaultUsageExtractor;
    }

    /**
     * Register a usage extractor for a provider.
     *
     * @param  UsageExtractorContract  $extractor  The extractor to register.
     */
    public function register(UsageExtractorContract $extractor): static
    {
        $this->extractors[$extractor->provider()] = $extractor;

        return $this;
    }

    /**
     * Get the extractor for a specific provider.
     *
     * @param  string  $provider  The provider name.
     * @return UsageExtractorContract The extractor (or default if not registered).
     */
    public function forProvider(string $provider): UsageExtractorContract
    {
        return $this->extractors[$provider] ?? $this->defaultExtractor;
    }

    /**
     * Extract usage data from a response for a specific provider.
     *
     * @param  string  $provider  The provider name.
     * @param  mixed  $response  The provider response.
     * @return array<string, mixed> Normalized usage data.
     */
    public function extract(string $provider, mixed $response): array
    {
        return $this->forProvider($provider)->extract($response);
    }
}
