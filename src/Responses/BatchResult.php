<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses;

use Atlasphp\Atlas\Enums\BatchResultStatus;
use Throwable;

/**
 * The outcome of a single request line within a batch job.
 *
 * On success, {@see $response} holds the parsed modality response
 * (a {@see TextResponse}, {@see EmbeddingsResponse}, etc.) produced by the
 * provider's normal response parser. On failure, {@see $error} holds the
 * mapped exception. {@see $customId} is the consumer key for trace-back.
 */
final class BatchResult
{
    public function __construct(
        public readonly string $customId,
        public readonly BatchResultStatus $status,
        public readonly ?object $response = null,
        public readonly ?Throwable $error = null,
        public readonly ?Usage $usage = null,
    ) {}

    /**
     * Whether this line produced a usable response.
     */
    public function succeeded(): bool
    {
        return $this->status->isSuccessful();
    }
}
