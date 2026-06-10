<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

/**
 * Dispatched before an HTTP request is sent to a provider.
 *
 * `$body` is the request payload and may contain user-supplied content
 * (prompts, messages). Auth headers and API keys are never included.
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
        public readonly ?string $correlationId = null,
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
    ) {}
}
