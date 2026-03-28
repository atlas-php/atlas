<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing;

use Atlasphp\Atlas\Responses\ModerationResponse;

/**
 * Fluent builder for creating fake ModerationResponse objects in tests.
 */
class ModerationResponseFake
{
    protected bool $flagged = false;

    /** @var array<string, mixed> */
    protected array $categories = [];

    /** @var array<string, mixed> */
    protected array $meta = [];

    public static function make(): self
    {
        return new self;
    }

    public function withFlagged(bool $flagged): static
    {
        $this->flagged = $flagged;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $categories
     */
    public function withCategories(array $categories): static
    {
        $this->categories = $categories;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function withMeta(array $meta): static
    {
        $this->meta = $meta;

        return $this;
    }

    public function toResponse(): ModerationResponse
    {
        return new ModerationResponse(
            flagged: $this->flagged,
            categories: $this->categories,
            meta: $this->meta,
        );
    }
}
