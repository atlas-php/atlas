<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Pending\Concerns\ResolvesProvider;
use Atlasphp\Atlas\Providers\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Providers\ModelList;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Providers\VoiceList;
use Atlasphp\Atlas\Responses\BatchResponse;
use Atlasphp\Atlas\Responses\BatchResult;

/**
 * Provider interrogation — delegates to the resolved driver for metadata queries.
 */
class ProviderRequest
{
    use ResolvesProvider;

    public function __construct(
        protected readonly Provider|string $provider,
        protected readonly ProviderRegistryContract $registry,
    ) {}

    public function models(): ModelList
    {
        return $this->resolveDriver()->models();
    }

    public function voices(): VoiceList
    {
        return $this->resolveDriver()->voices();
    }

    public function validate(): bool
    {
        return $this->resolveDriver()->validate();
    }

    public function capabilities(): ProviderCapabilities
    {
        return $this->resolveDriver()->capabilities();
    }

    /**
     * Fetch the current state of a batch job (stateless polling).
     */
    public function batchStatus(string $batchId): BatchResponse
    {
        return $this->resolveDriver()->batchStatus($batchId);
    }

    /**
     * Stream the per-line results of a completed batch job.
     *
     * @return iterable<int, BatchResult>
     */
    public function batchResults(string $batchId): iterable
    {
        return $this->resolveDriver()->batchResults($batchId);
    }

    /**
     * Request cancellation of an in-flight batch job.
     */
    public function batchCancel(string $batchId): BatchResponse
    {
        return $this->resolveDriver()->batchCancel($batchId);
    }

    public function name(): string
    {
        return $this->resolveDriver()->name();
    }

    /**
     * ProviderRequest has no model — always returns empty string.
     */
    protected function resolveModelKey(): string
    {
        return '';
    }
}
