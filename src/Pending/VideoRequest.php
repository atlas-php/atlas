<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use Atlasphp\Atlas\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Pending\Concerns\HasMeta;
use Atlasphp\Atlas\Pending\Concerns\HasMiddleware;
use Atlasphp\Atlas\Pending\Concerns\ResolvesProvider;
use Atlasphp\Atlas\Requests\VideoRequest as VideoRequestObject;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\VideoResponse;

/**
 * Fluent builder for video generation and video-to-text requests.
 */
class VideoRequest
{
    use HasMeta;
    use HasMiddleware;
    use ResolvesProvider;

    protected ?string $instructions = null;

    /** @var array<int, mixed> */
    protected array $media = [];

    protected ?int $duration = null;

    protected ?string $ratio = null;

    protected ?string $format = null;

    /** @var array<string, mixed> */
    protected array $providerOptions = [];

    public function __construct(
        protected readonly Provider|string $provider,
        protected readonly ?string $model,
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

    public function withDuration(int $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function withRatio(string $ratio): static
    {
        $this->ratio = $ratio;

        return $this;
    }

    public function withFormat(string $format): static
    {
        $this->format = $format;

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

    public function asVideo(): VideoResponse
    {
        $driver = $this->resolveDriver();
        $this->ensureCapability($driver, 'video');

        return $driver->video($this->buildRequest());
    }

    public function asText(): TextResponse
    {
        $driver = $this->resolveDriver();
        $this->ensureCapability($driver, 'videoToText');

        return $driver->videoToText($this->buildRequest());
    }

    public function buildRequest(): VideoRequestObject
    {
        return new VideoRequestObject(
            model: $this->model ?? '',
            instructions: $this->instructions,
            media: $this->media,
            duration: $this->duration,
            ratio: $this->ratio,
            format: $this->format,
            providerOptions: $this->providerOptions,
            middleware: $this->middleware,
            meta: $this->meta,
        );
    }
}
