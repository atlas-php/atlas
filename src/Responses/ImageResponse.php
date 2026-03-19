<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses;

/**
 * Response from an image generation request.
 */
class ImageResponse
{
    /**
     * @param  string|array<int, string>  $url
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string|array $url,
        public readonly ?string $revisedPrompt = null,
        public readonly array $meta = [],
    ) {}
}
