<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending\Contracts;

use Atlasphp\Atlas\Enums\Modality;

/**
 * A pending modality builder that can be enqueued as a line in a batch job.
 *
 * Implemented only by the modality builders whose requests a provider batch
 * endpoint accepts (text, embeddings). Builders that cannot be batched (audio,
 * voice, rerank, …) deliberately do not implement this, so the batch builder's
 * add() method rejects them at build time.
 */
interface Batchable
{
    /**
     * The modality this request represents (determines the batch endpoint).
     */
    public function batchModality(): Modality;

    /**
     * The resolved provider key this request targets.
     */
    public function batchProvider(): string;

    /**
     * Build the immutable request DTO serialized into the batch payload.
     */
    public function buildRequest(): object;
}
