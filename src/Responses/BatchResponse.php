<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses;

use Atlasphp\Atlas\Enums\BatchStatus;

/**
 * The state of a batch job as reported by the provider.
 *
 * Returned by submission and status polling. Carries the provider batch id
 * (the handle for all follow-up calls), the normalized status, and aggregate
 * counts. Per-line results are fetched separately as {@see BatchResult} via the
 * handler's results() method. Usage is the rolled-up token total, available
 * once the job has produced results.
 */
final class BatchResponse
{
    public function __construct(
        public readonly string $batchId,
        public readonly BatchStatus $status,
        public readonly RequestCounts $counts,
        public readonly ?string $inputFileId = null,
        public readonly ?string $outputFileId = null,
        public readonly ?Usage $usage = null,
        public readonly ?string $error = null,
    ) {}

    // isTerminal()/isSuccessful() deliberately forward to the status enum so a
    // consumer holding a BatchResponse can ask the common questions directly
    // (e.g. `$response->isTerminal()`) without reaching through `->status`.

    /**
     * Whether the job has reached a state that will not change further.
     */
    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * Whether the job finished successfully and its results can be ingested.
     */
    public function isSuccessful(): bool
    {
        return $this->status->isSuccessful();
    }
}
