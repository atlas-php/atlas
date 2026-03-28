<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Requests;

/**
 * Request object for video generation.
 */
final class VideoRequest
{
    /**
     * @param  array<int, mixed>  $media
     * @param  array<string, mixed>  $providerOptions
     * @param  array<int, mixed>  $middleware
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $model,
        public readonly ?string $instructions,
        public readonly array $media,
        public readonly ?int $duration,
        public readonly ?string $ratio,
        public readonly ?string $format,
        public readonly array $providerOptions = [],
        public readonly array $middleware = [],
        public readonly array $meta = [],
    ) {}
}
