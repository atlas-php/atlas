<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing;

use Atlasphp\Atlas\Responses\VideoResponse;

/**
 * Fluent builder for creating fake VideoResponse objects in tests.
 */
class VideoResponseFake
{
    protected string $url = 'https://fake.atlas/video.mp4';

    protected ?int $duration = null;

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

    public function withDuration(?int $duration): static
    {
        $this->duration = $duration;

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

    public function toResponse(): VideoResponse
    {
        return new VideoResponse(
            url: $this->url,
            duration: $this->duration,
            meta: $this->meta,
        );
    }
}
