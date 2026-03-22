<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Illuminate\Http\Client\Response;

/**
 * Dispatched after an HTTP request to a provider fails.
 */
class ProviderRequestFailed
{
    public function __construct(
        public readonly string $url,
        public readonly Response $response,
    ) {}
}
