<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Http;

/**
 * Identifying context for a single logical provider HTTP call.
 *
 * Handlers supply the provider key and model so the transport events
 * (ProviderRequestStarted/Completed/Failed/Retrying) can be attributed without
 * parsing the URL. The HttpClient stamps a correlation id that stays stable
 * across retries, letting consumers tie a request's whole lifecycle together.
 */
final class ProviderRequestContext
{
    public function __construct(
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly ?string $correlationId = null,
    ) {}

    /**
     * Return a copy carrying the given correlation id.
     */
    public function withCorrelationId(string $correlationId): self
    {
        return new self($this->provider, $this->model, $correlationId);
    }
}
