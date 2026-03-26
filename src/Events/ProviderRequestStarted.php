<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

/**
 * Dispatched before an HTTP request is sent to a provider.
 */
class ProviderRequestStarted
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        public readonly string $url,
        public readonly array $body,
        public readonly string $method = 'POST',
    ) {}
}
