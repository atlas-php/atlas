<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing;

use Atlasphp\Atlas\Responses\ImageResponse;

/**
 * Fluent builder for creating fake ImageResponse objects in tests.
 */
class ImageResponseFake
{
    protected string $url = 'https://fake.atlas/image.png';

    protected ?string $revisedPrompt = null;

    /** @var array<string, mixed> */
    protected array $meta = [];

    public static function make(): self
    {
        return new self;
    }

    public function withUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function withRevisedPrompt(?string $revisedPrompt): static
    {
        $this->revisedPrompt = $revisedPrompt;

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

    public function toResponse(): ImageResponse
    {
        return new ImageResponse(
            url: $this->url,
            revisedPrompt: $this->revisedPrompt,
            meta: $this->meta,
        );
    }
}
