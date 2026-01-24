<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Support;

use Atlasphp\Atlas\Agents\Enums\MediaSource;
use Atlasphp\Atlas\Agents\Enums\MediaType;
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
 * Supports both array format (for serialization/queues) and direct Prism
 * media objects (for direct API access).
 */
trait HasMediaSupport
{
    /**
     * Current input attachments (array format for serialization).
     *
     * @var array<int, array{type: string, source: string, data: string, mime_type?: string|null, title?: string|null, disk?: string|null}>
     */
    private array $currentAttachments = [];

    /**
     * Direct Prism media objects.
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
        return $this->addAttachments(MediaType::Image, $data, $source, $mimeType, null, $disk);
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
        return $this->addAttachments(MediaType::Document, $data, $source, $mimeType, $title, $disk);
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
        return $this->addAttachments(MediaType::Audio, $data, $source, $mimeType, null, $disk);
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
        return $this->addAttachments(MediaType::Video, $data, $source, $mimeType, null, $disk);
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
     * Get the current attachments (array format).
     *
     * @return array<int, array{type: string, source: string, data: string, mime_type?: string|null, title?: string|null, disk?: string|null}>
     */
    protected function getCurrentAttachments(): array
    {
        return $this->currentAttachments;
    }

    /**
     * Get the direct Prism media objects.
     *
     * @return array<int, Image|Document|Audio|Video>
     */
    protected function getPrismMedia(): array
    {
        return $this->prismMedia;
    }

    /**
     * Add attachments of a specific type.
     *
     * @param  string|array<int, string>  $data  Single item or array of items.
     */
    protected function addAttachments(
        MediaType $type,
        string|array $data,
        MediaSource $source,
        ?string $mimeType = null,
        ?string $title = null,
        ?string $disk = null,
    ): static {
        $clone = clone $this;

        $items = is_array($data) ? $data : [$data];

        foreach ($items as $item) {
            $attachment = [
                'type' => $type->value,
                'source' => $source->value,
                'data' => $item,
            ];

            if ($mimeType !== null) {
                $attachment['mime_type'] = $mimeType;
            }

            if ($title !== null) {
                $attachment['title'] = $title;
            }

            if ($disk !== null) {
                $attachment['disk'] = $disk;
            }

            $clone->currentAttachments[] = $attachment;
        }

        return $clone;
    }
}
