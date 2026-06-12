<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Requests;

/**
 * A single request within a batch job.
 *
 * Pairs a consumer-supplied key with the immutable modality request DTO it
 * wraps (a {@see TextRequest}, {@see EmbedRequest}, etc.). The key is echoed
 * back by the provider on the matching result, so consumers trace each result
 * to their own record regardless of completion order.
 */
final class BatchLine
{
    public function __construct(
        public readonly string $customId,
        public readonly object $request,
    ) {}
}
