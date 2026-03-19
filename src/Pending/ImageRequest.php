<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Pending\Concerns\ResolvesProvider;
use Atlasphp\Atlas\Requests\ImageRequest as ImageRequestObject;
use Atlasphp\Atlas\Responses\ImageResponse;
use Atlasphp\Atlas\Responses\TextResponse;

/**
 * Fluent builder for image generation and image-to-text requests.
 */
class ImageRequest
{
    use ResolvesProvider;

    protected ?string $instructions = null;

    /** @var array<int, mixed> */
    protected array $media = [];

    protected ?string $size = null;

    protected ?string $quality = null;

    protected ?string $format = null;

    protected int $count = 1;

    /** @var array<string, mixed> */
    protected array $providerOptions = [];

    public function __construct(
        protected readonly Provider|string $provider,
        protected readonly string $model,
        protected readonly ProviderRegistryContract $registry,
    ) {}

    public function instructions(string $instructions): static
    {
        $this->instructions = $instructions;

        return $this;
    }

    /**
     * @param  array<int, mixed>  $media
     */
    public function withMedia(array $media): static
    {
        $this->media = $media;

        return $this;
    }

    public function withSize(string $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function withQuality(string $quality): static
    {
        $this->quality = $quality;

        return $this;
    }

    public function withFormat(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function withCount(int $count): static
    {
        $this->count = $count;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function withProviderOptions(array $options): static
    {
        $this->providerOptions = $options;

        return $this;
    }

    public function asImage(): ImageResponse
    {
        $driver = $this->resolveDriver();
        $this->ensureCapability($driver, 'image');

        return $driver->image($this->buildRequest());
    }

    public function asText(): TextResponse
    {
        $driver = $this->resolveDriver();
        $this->ensureCapability($driver, 'imageToText');

        return $driver->imageToText($this->buildRequest());
    }

    public function buildRequest(): ImageRequestObject
    {
        return new ImageRequestObject(
            model: $this->model,
            instructions: $this->instructions,
            media: $this->media,
            size: $this->size,
            quality: $this->quality,
            format: $this->format,
            providerOptions: $this->providerOptions,
            count: $this->count,
        );
    }
}
