<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Requests;

use Atlasphp\Atlas\Enums\Modality;

/**
 * Immutable request object for submitting a batch job.
 *
 * A batch targets a single provider and a single modality (provider batch
 * endpoints accept one endpoint/method per job); models may still vary per
 * line. Each line is an independent request serialized into the provider's
 * batch payload by the provider's batch handler.
 */
final class Batch
{
    /**
     * @param  array<int, BatchLine>  $lines
     */
    public function __construct(
        public readonly string $provider,
        public readonly Modality $modality,
        public readonly array $lines,
        public readonly string $completionWindow = '24h',
    ) {}

    /**
     * Number of request lines in the batch.
     */
    public function count(): int
    {
        return count($this->lines);
    }
}
