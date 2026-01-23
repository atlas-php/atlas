<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Providers\Services;

use Atlasphp\Atlas\Providers\Enums\MediaSource;
use Atlasphp\Atlas\Providers\Enums\MediaType;
use InvalidArgumentException;
use Prism\Prism\ValueObjects\Media\Audio;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Media\Video;

/**
 * Service to convert attachment arrays to Prism Media objects.
 *
 * Converts the serializable attachment format used by Atlas into the
 * corresponding Prism media objects for API requests.
 */
class MediaConverter
{
    /**
     * Convert a single attachment array to a Prism Media object.
     *
     * @param  array{type: string, source: string, data: string, mime_type?: string|null, title?: string|null, disk?: string|null}  $attachment
     *
     * @throws InvalidArgumentException If the attachment format is invalid.
     */
    public function convert(array $attachment): Image|Document|Audio|Video
    {
        $this->validateAttachment($attachment);

        $type = MediaType::from($attachment['type']);
        $source = MediaSource::from($attachment['source']);
        $data = $attachment['data'];
        $mimeType = $attachment['mime_type'] ?? null;
        $title = $attachment['title'] ?? null;
        $disk = $attachment['disk'] ?? null;

        return match ($type) {
            MediaType::Image => $this->createImage($source, $data, $mimeType),
            MediaType::Document => $this->createDocument($source, $data, $mimeType, $title, $disk),
            MediaType::Audio => $this->createAudio($source, $data, $mimeType),
            MediaType::Video => $this->createVideo($source, $data, $mimeType),
        };
    }

    /**
     * Convert multiple attachment arrays to Prism Media objects.
     *
     * @param  array<int, array{type: string, source: string, data: string, mime_type?: string|null, title?: string|null, disk?: string|null}>  $attachments
     * @return array<int, Image|Document|Audio|Video>
     */
    public function convertMany(array $attachments): array
    {
        $converted = [];

        foreach ($attachments as $attachment) {
            $converted[] = $this->convert($attachment);
        }

        return $converted;
    }

    /**
     * Validate the attachment array structure.
     *
     * @param  array<string, mixed>  $attachment
     *
     * @throws InvalidArgumentException If required fields are missing.
     */
    protected function validateAttachment(array $attachment): void
    {
        if (! isset($attachment['type'])) {
            throw new InvalidArgumentException('Attachment must have a "type" field.');
        }

        if (! isset($attachment['source'])) {
            throw new InvalidArgumentException('Attachment must have a "source" field.');
        }

        if (! isset($attachment['data'])) {
            throw new InvalidArgumentException('Attachment must have a "data" field.');
        }
    }

    /**
     * Create an Image from the given source and data.
     */
    protected function createImage(MediaSource $source, string $data, ?string $mimeType): Image
    {
        return match ($source) {
            MediaSource::Url => Image::fromUrl($data, $mimeType),
            MediaSource::Base64 => Image::fromBase64($data, $mimeType),
            MediaSource::LocalPath => Image::fromLocalPath($data, $mimeType),
            MediaSource::StoragePath => Image::fromStoragePath($data),
            MediaSource::FileId => Image::fromFileId($data),
        };
    }

    /**
     * Create a Document from the given source and data.
     */
    protected function createDocument(
        MediaSource $source,
        string $data,
        ?string $mimeType,
        ?string $title,
        ?string $disk,
    ): Document {
        return match ($source) {
            MediaSource::Url => Document::fromUrl($data, $title),
            MediaSource::Base64 => Document::fromBase64($data, $mimeType, $title),
            MediaSource::LocalPath => Document::fromLocalPath($data, $title),
            MediaSource::StoragePath => Document::fromStoragePath($data, $disk, $title),
            MediaSource::FileId => Document::fromFileId($data, $title),
        };
    }

    /**
     * Create an Audio from the given source and data.
     */
    protected function createAudio(MediaSource $source, string $data, ?string $mimeType): Audio
    {
        return match ($source) {
            MediaSource::Url => Audio::fromUrl($data, $mimeType),
            MediaSource::Base64 => Audio::fromBase64($data, $mimeType),
            MediaSource::LocalPath => Audio::fromLocalPath($data, $mimeType),
            MediaSource::StoragePath => Audio::fromStoragePath($data),
            MediaSource::FileId => Audio::fromFileId($data),
        };
    }

    /**
     * Create a Video from the given source and data.
     */
    protected function createVideo(MediaSource $source, string $data, ?string $mimeType): Video
    {
        return match ($source) {
            MediaSource::Url => Video::fromUrl($data, $mimeType),
            MediaSource::Base64 => Video::fromBase64($data, $mimeType),
            MediaSource::LocalPath => Video::fromLocalPath($data, $mimeType),
            MediaSource::StoragePath => Video::fromStoragePath($data),
            MediaSource::FileId => Video::fromFileId($data),
        };
    }
}
