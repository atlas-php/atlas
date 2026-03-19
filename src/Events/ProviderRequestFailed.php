<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

/**
 * Dispatched after an HTTP request to a provider fails.
 */
class ProviderRequestFailed
{
    public function __construct(
        public readonly string $url,
        public readonly mixed $response,
    ) {}
}
