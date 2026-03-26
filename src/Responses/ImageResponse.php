<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses;

use Atlasphp\Atlas\Concerns\StoresMedia;
use Atlasphp\Atlas\Responses\Contracts\Storable;

/**
 * Response from an image generation request.
 */
class ImageResponse implements Storable
{
    use StoresMedia;

    /**
     * The stored asset record, set by TrackProviderCall when persistence is enabled.
     * Typed as ?object to avoid coupling Responses to the Persistence layer.
     */
    public ?object $asset = null;

    /**
     * @param  string|array<int, string>  $url
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string|array $url,
        public readonly ?string $revisedPrompt = null,
        public readonly array $meta = [],
        public readonly ?string $base64 = null,
        public readonly ?string $format = null,
    ) {}

    /**
     * @return array{type: string, value: string, disk?: string|null}
     */
    protected function mediaSource(): array
    {
        if ($this->base64 !== null) {
            return ['type' => 'base64', 'value' => $this->base64];
        }

        $url = is_array($this->url) ? $this->url[0] : $this->url;

        return ['type' => 'url', 'value' => $url];
    }

    protected function defaultExtension(): string
    {
        return $this->format ?? 'png';
    }
}
