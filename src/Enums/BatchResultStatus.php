<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Enums;

/**
 * Outcome of a single request line within a batch job.
 *
 * Independent per line: one errored line never affects the others. Each batch
 * handler maps the provider's per-result outcome into this enum.
 */
enum BatchResultStatus: string
{
    case Succeeded = 'succeeded';
    case Errored = 'errored';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /**
     * Whether this line produced a usable response.
     */
    public function isSuccessful(): bool
    {
        return $this === self::Succeeded;
    }
}
