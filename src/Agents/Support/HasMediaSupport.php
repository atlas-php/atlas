<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Support;

use Atlasphp\Atlas\Agents\Enums\MediaSource;
use Prism\Prism\ValueObjects\Media\Audio;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Media\Video;

/**
 * Trait for services that support media attachments.
 *
 * Provides fluent methods for attaching images, documents, audio, and video
 * to requests. Each method accepts a single item or an array of items.
 * Uses the clone pattern for immutability.
 *
 * All methods create Prism media objects directly, deferring to Prism
 * for media handling as per the Atlas philosophy.
 */
trait HasMediaSupport
{
    /**
     * Prism media objects for current input.
     *
     * @var array<int, Image|Document|Audio|Video>
     */
    private array $prismMedia = [];

    /**
     * Attach one or more images to the request.
     *
     * @param  string|array<int, string>  $data  Single URL/path/data or array of them.
     * @param  MediaSource  $source  The source type (default: URL).
     * @param  string|null  $mimeType  Optional MIME type.
     * @param  string|null  $disk  Optional storage disk (for StoragePath source).
     */
    public function withImage(
        string|array $data,
        MediaSource $source = MediaSource::Url,
        ?string $mimeType = null,
        ?string $disk = null,
    ): static {
        $clone = clone $this;
        $items = is_array($data) ? $data : [$data];

        foreach ($items as $item) {
            $clone->prismMedia[] = match ($source) {
                MediaSource::Url => Image::fromUrl($item, $mimeType),
                MediaSource::Base64 => Image::fromBase64($item, $mimeType),
                MediaSource::LocalPath => Image::fromLocalPath($item, $mimeType),
                MediaSource::StoragePath => Image::fromStoragePath($item, $disk),
                MediaSource::FileId => Image::fromFileId($item),
            };
        }

        return $clone;
    }

    /**
     * Attach one or more documents to the request.
     *
     * @param  string|array<int, string>  $data  Single URL/path/data or array of them.
     * @param  MediaSource  $source  The source type (default: URL).
     * @param  string|null  $mimeType  Optional MIME type.
     * @param  string|null  $title  Optional document title.
     * @param  string|null  $disk  Optional storage disk (for StoragePath source).
     */
    public function withDocument(
        string|array $data,
        MediaSource $source = MediaSource::Url,
        ?string $mimeType = null,
        ?string $title = null,
        ?string $disk = null,
    ): static {
        $clone = clone $this;
        $items = is_array($data) ? $data : [$data];

        foreach ($items as $item) {
            $clone->prismMedia[] = match ($source) {
                MediaSource::Url => Document::fromUrl($item, $title),
                MediaSource::Base64 => Document::fromBase64($item, $mimeType, $title),
                MediaSource::LocalPath => Document::fromLocalPath($item, $title),
                MediaSource::StoragePath => Document::fromStoragePath($item, $disk, $title),
                MediaSource::FileId => Document::fromFileId($item, $title),
            };
        }

        return $clone;
    }

    /**
     * Attach one or more audio files to the request.
     *
     * @param  string|array<int, string>  $data  Single URL/path/data or array of them.
     * @param  MediaSource  $source  The source type (default: URL).
     * @param  string|null  $mimeType  Optional MIME type.
     * @param  string|null  $disk  Optional storage disk (for StoragePath source).
     */
    public function withAudio(
        string|array $data,
        MediaSource $source = MediaSource::Url,
        ?string $mimeType = null,
        ?string $disk = null,
    ): static {
        $clone = clone $this;
        $items = is_array($data) ? $data : [$data];

        foreach ($items as $item) {
            $clone->prismMedia[] = match ($source) {
                MediaSource::Url => Audio::fromUrl($item, $mimeType),
                MediaSource::Base64 => Audio::fromBase64($item, $mimeType),
                MediaSource::LocalPath => Audio::fromLocalPath($item, $mimeType),
                MediaSource::StoragePath => Audio::fromStoragePath($item, $disk),
                MediaSource::FileId => Audio::fromFileId($item),
            };
        }

        return $clone;
    }

    /**
     * Attach one or more video files to the request.
     *
     * @param  string|array<int, string>  $data  Single URL/path/data or array of them.
     * @param  MediaSource  $source  The source type (default: URL).
     * @param  string|null  $mimeType  Optional MIME type.
     * @param  string|null  $disk  Optional storage disk (for StoragePath source).
     */
    public function withVideo(
        string|array $data,
        MediaSource $source = MediaSource::Url,
        ?string $mimeType = null,
        ?string $disk = null,
    ): static {
        $clone = clone $this;
        $items = is_array($data) ? $data : [$data];

        foreach ($items as $item) {
            $clone->prismMedia[] = match ($source) {
                MediaSource::Url => Video::fromUrl($item, $mimeType),
                MediaSource::Base64 => Video::fromBase64($item, $mimeType),
                MediaSource::LocalPath => Video::fromLocalPath($item, $mimeType),
                MediaSource::StoragePath => Video::fromStoragePath($item, $disk),
                MediaSource::FileId => Video::fromFileId($item),
            };
        }

        return $clone;
    }

    /**
     * Attach Prism media objects directly.
     *
     * This allows direct use of Prism's media objects for full API access:
     * - Image::fromUrl(), Image::fromBase64(), Image::fromPath()
     * - Document::fromUrl(), Document::fromBase64(), Document::fromPath()
     * - Audio::fromUrl(), Audio::fromBase64(), Audio::fromPath()
     * - Video::fromUrl(), Video::fromBase64(), Video::fromPath()
     *
     * @param  Image|Document|Audio|Video|array<int, Image|Document|Audio|Video>  $media
     */
    public function withMedia(Image|Document|Audio|Video|array $media): static
    {
        $clone = clone $this;
        $items = is_array($media) ? $media : [$media];

        foreach ($items as $item) {
            $clone->prismMedia[] = $item;
        }

        return $clone;
    }

    /**
     * Get the Prism media objects.
     *
     * @return array<int, Image|Document|Audio|Video>
     */
    protected function getPrismMedia(): array
    {
        return $this->prismMedia;
    }
}
