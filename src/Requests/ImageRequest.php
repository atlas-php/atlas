<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Requests;

/**
 * Request object for image generation.
 */
class ImageRequest
{
    /**
     * @param  array<int, mixed>  $media
     * @param  array<string, mixed>  $providerOptions
     */
    public function __construct(
        public readonly string $model,
        public readonly ?string $instructions,
        public readonly array $media,
        public readonly ?string $size,
        public readonly ?string $quality,
        public readonly ?string $format,
        public readonly array $providerOptions = [],
    ) {}
}
