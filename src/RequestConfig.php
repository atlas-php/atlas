<?php

declare(strict_types=1);

namespace Atlasphp\Atlas;

/**
 * Per-call request configuration for timeout and retry behaviour.
 *
 * Starts from global AtlasConfig defaults and can be overridden via
 * the fluent API (->withTimeout(), ->withRetry(), ->withoutRetry()).
 * Immutable — every override returns a new instance.
 */
class RequestConfig
{
    public function __construct(
        public readonly int $timeout,
        public readonly int $rateLimit,
        public readonly int $errors,
    ) {}

    /**
     * Build from global config defaults.
     */
    public static function fromAtlasConfig(AtlasConfig $config): self
    {
        return new self(
            timeout: $config->retryTimeout,
            rateLimit: $config->retryRateLimit,
            errors: $config->retryErrors,
        );
    }

    /**
     * Override the timeout for this call.
     */
    public function withTimeout(int $seconds): self
    {
        return new self($seconds, $this->rateLimit, $this->errors);
    }

    /**
     * Override retry counts. Unspecified values remain unchanged.
     */
    public function withRetry(?int $rateLimit = null, ?int $errors = null): self
    {
        return new self(
            $this->timeout,
            $rateLimit ?? $this->rateLimit,
            $errors ?? $this->errors,
        );
    }

    /**
     * Disable all retry. Exceptions surface immediately.
     */
    public function withoutRetry(): self
    {
        return new self($this->timeout, 0, 0);
    }

    /**
     * Whether any retry is enabled.
     */
    public function retryEnabled(): bool
    {
        return $this->rateLimit > 0 || $this->errors > 0;
    }
}
