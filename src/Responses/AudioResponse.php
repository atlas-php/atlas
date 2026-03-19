<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses;

/**
 * Response from an audio generation request.
 */
class AudioResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $data,
        public readonly ?string $format = null,
        public readonly array $meta = [],
    ) {}
}
