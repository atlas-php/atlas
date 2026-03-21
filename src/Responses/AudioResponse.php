<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Responses;

use Atlasphp\Atlas\Concerns\StoresMedia;

/**
 * Response from an audio generation request.
 */
class AudioResponse
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
        public readonly string $data,
        public readonly ?string $format = null,
        public readonly array $meta = [],
    ) {}

    /**
     * Get the raw binary audio data as a string.
     */
    public function __toString(): string
    {
        return $this->contents();
    }

    /**
     * @return array{type: string, value: string, disk?: string|null}
     */
    protected function mediaSource(): array
    {
        return ['type' => 'base64', 'value' => $this->data];
    }

    protected function defaultExtension(): string
    {
        return $this->format ?? 'mp3';
    }
}
