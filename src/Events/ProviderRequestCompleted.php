<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

/**
 * Dispatched after a successful HTTP response from a provider.
 */
class ProviderRequestCompleted
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $url,
        public readonly array $data,
        public readonly int $statusCode = 200,
    ) {}
}
