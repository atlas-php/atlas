<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses;

/**
 * Aggregate per-line counts for a batch job.
 */
final class RequestCounts
{
    public function __construct(
        public readonly int $total = 0,
        public readonly int $succeeded = 0,
        public readonly int $failed = 0,
        public readonly int $processing = 0,
    ) {}

    /**
     * Convert to an array for JSON persistence.
     *
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'succeeded' => $this->succeeded,
            'failed' => $this->failed,
            'processing' => $this->processing,
        ];
    }

    /**
     * Create from a persisted array.
     *
     * @param  array<string, int>|null  $data
     */
    public static function fromArray(?array $data): self
    {
        if ($data === null) {
            return new self;
        }

        return new self(
            total: $data['total'] ?? 0,
            succeeded: $data['succeeded'] ?? 0,
            failed: $data['failed'] ?? 0,
            processing: $data['processing'] ?? 0,
        );
    }
}
