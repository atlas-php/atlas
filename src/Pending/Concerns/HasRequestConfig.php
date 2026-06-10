<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending\Concerns;

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\RequestConfig;

/**
 * Adds per-call timeout and retry configuration to pending request builders.
 *
 * Provides withTimeout(), withRetry(), and withoutRetry() fluent methods.
 * The resolved RequestConfig is passed through to the HTTP layer.
 */
trait HasRequestConfig
{
    protected ?RequestConfig $requestConfig = null;

    /**
     * Override the timeout for this call only.
     *
     *   ->withTimeout(120)   // wait up to 2 minutes
     *   ->withTimeout(10)    // fail fast on a real-time path
     */
    public function withTimeout(int $seconds): static
    {
        $this->requestConfig = $this->resolveRequestConfig()->withTimeout($seconds);

        return $this;
    }

    /**
     * Override retry behaviour for this call only.
     * Unspecified values remain at config defaults.
     *
     *   ->withRetry(rateLimit: 5)          // more patient on rate limits
     *   ->withRetry(errors: 0)             // disable error retry only
     *   ->withRetry(rateLimit: 5, errors: 3)
     */
    public function withRetry(?int $rateLimit = null, ?int $errors = null): static
    {
        $this->requestConfig = $this->resolveRequestConfig()->withRetry($rateLimit, $errors);

        return $this;
    }

    /**
     * Disable all retry for this call. Exceptions surface immediately.
     */
    public function withoutRetry(): static
    {
        $this->requestConfig = $this->resolveRequestConfig()->withoutRetry();

        return $this;
    }

    /**
     * Get the resolved request config, defaulting from AtlasConfig.
     */
    protected function resolveRequestConfig(): RequestConfig
    {
        return $this->requestConfig
            ?? RequestConfig::fromAtlasConfig(app(AtlasConfig::class));
    }

    /**
     * Serialize the per-call config override for a queue payload.
     *
     * Returns null when no override was set, so queued jobs without an explicit
     * ->withRetry()/->withTimeout()/->withoutRetry() fall back to global config.
     *
     * @return array{timeout: int, rateLimit: int, errors: int, timeoutExplicit: bool}|null
     */
    public function requestConfigPayload(): ?array
    {
        return $this->requestConfig?->toArray();
    }

    /**
     * Restore a per-call config override from a queue payload.
     *
     * @param  array{timeout: int, rateLimit: int, errors: int, timeoutExplicit?: bool}|null  $data
     */
    public function applyRequestConfigPayload(?array $data): void
    {
        if ($data !== null) {
            $this->requestConfig = RequestConfig::fromArray($data);
        }
    }
}
