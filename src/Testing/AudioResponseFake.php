<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Testing;

use Atlasphp\Atlas\Responses\AudioResponse;

/**
 * Fluent builder for creating fake AudioResponse objects in tests.
 */
class AudioResponseFake
{
    protected string $data;

    protected ?string $format = 'mp3';

    /** @var array<string, mixed> */
    protected array $meta = [];

    public function __construct()
    {
        $this->data = base64_encode('fake-audio');
    }

    public static function make(): self
    {
        return new self;
    }

    public function withData(string $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function withFormat(?string $format): static
    {
        $this->format = $format;

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

    public function toResponse(): AudioResponse
    {
        return new AudioResponse(
            data: $this->data,
            format: $this->format,
            meta: $this->meta,
        );
    }
}
