<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses;

/**
 * Response from a content moderation request.
 */
class ModerationResponse
{
    /**
     * @param  array<string, mixed>  $categories
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly bool $flagged,
        public readonly array $categories = [],
        public readonly array $meta = [],
    ) {}
}
