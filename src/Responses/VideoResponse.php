<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses;

use Atlasphp\Atlas\Concerns\StoresMedia;
use Atlasphp\Atlas\Providers\Contracts\HasContents;

/**
 * Response from a video generation request.
 */
class VideoResponse implements HasContents
{
    use StoresMedia;

    /**
     * The stored asset record, set by TrackProviderCall when persistence is enabled.
     * Typed as ?object to avoid coupling Responses to the Persistence layer.
     */
    public ?object $asset = null;

    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $url,
        public readonly ?int $duration = null,
        public readonly array $meta = [],
        public readonly ?string $format = null,
    ) {}

    /**
     * @return array{type: string, value: string, disk?: string|null}
     */
    protected function mediaSource(): array
    {
        return ['type' => 'url', 'value' => $this->url];
    }

    protected function defaultExtension(): string
    {
        return $this->format ?? 'mp4';
    }
}
