<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses;

/**
 * Response from a video generation request.
 */
class VideoResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $url,
        public readonly ?int $duration = null,
        public readonly array $meta = [],
    ) {}
}
