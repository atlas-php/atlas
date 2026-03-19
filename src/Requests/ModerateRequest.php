<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Requests;

/**
 * Request object for content moderation.
 */
class ModerateRequest
{
    /**
     * @param  string|array<int, string>  $input
     * @param  array<string, mixed>  $providerOptions
     * @param  array<int, mixed>  $middleware
     */
    public function __construct(
        public readonly string $model,
        public readonly string|array $input,
        public readonly array $providerOptions = [],
        public readonly array $middleware = [],
    ) {}
}
