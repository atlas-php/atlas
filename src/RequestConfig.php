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
        public readonly bool $timeoutExplicit = false,
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
     * Rehydrate from a queue payload.
     *
     * @param  array{timeout: int, rateLimit: int, errors: int, timeoutExplicit?: bool}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            timeout: (int) $data['timeout'],
            rateLimit: (int) $data['rateLimit'],
            errors: (int) $data['errors'],
            timeoutExplicit: (bool) ($data['timeoutExplicit'] ?? false),
        );
    }

    /**
     * Serialize for a queue payload.
     *
     * @return array{timeout: int, rateLimit: int, errors: int, timeoutExplicit: bool}
     */
    public function toArray(): array
    {
        return [
            'timeout' => $this->timeout,
            'rateLimit' => $this->rateLimit,
            'errors' => $this->errors,
            'timeoutExplicit' => $this->timeoutExplicit,
        ];
    }

    /**
     * Override the timeout for this call. Marks the timeout as explicit so the
     * HTTP layer applies it in place of the handler's default (which may be a
     * longer provider/reasoning/media timeout).
     */
    public function withTimeout(int $seconds): self
    {
        return new self($seconds, $this->rateLimit, $this->errors, timeoutExplicit: true);
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
            $this->timeoutExplicit,
        );
    }

    /**
     * Disable all retry. Exceptions surface immediately.
     */
    public function withoutRetry(): self
    {
        return new self($this->timeout, 0, 0, $this->timeoutExplicit);
    }

    /**
     * Whether any retry is enabled.
     */
    public function retryEnabled(): bool
    {
        return $this->rateLimit > 0 || $this->errors > 0;
    }
}
