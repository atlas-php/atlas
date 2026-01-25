<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Support;

use Prism\Prism\ValueObjects\Media\Audio;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Media\Video;

/**
 * Trait for services that support media attachments via Prism objects.
 *
 * For attaching media to current input, pass Prism media objects directly
 * to chat() or stream() for a Prism-consistent API:
 *
 * ```php
 * use Prism\Prism\ValueObjects\Media\Image;
 *
 * Atlas::agent('vision')
 *     ->chat('Describe this image', [
 *         Image::fromUrl('https://example.com/photo.jpg'),
 *     ]);
 * ```
 *
 * @see https://prismphp.com/input-modalities/images.html
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
     * Attach Prism media objects using the builder pattern.
     *
     * For a more Prism-consistent API, prefer passing attachments
     * directly to chat() or stream():
     *
     * ```php
     * // Prism-style (recommended):
     * ->chat('Describe this image', [Image::fromUrl('https://...')])
     *
     * // Builder-style (also supported):
     * ->withMedia([Image::fromUrl('https://...')])
     * ->chat('Describe this image')
     * ```
     *
     * Prism media objects:
     * - Image::fromUrl(), Image::fromBase64(), Image::fromLocalPath(), Image::fromStoragePath(), Image::fromFileId()
     * - Document::fromUrl(), Document::fromBase64(), Document::fromLocalPath(), Document::fromStoragePath(), Document::fromFileId()
     * - Audio::fromUrl(), Audio::fromBase64(), Audio::fromLocalPath(), Audio::fromStoragePath(), Audio::fromFileId()
     * - Video::fromUrl(), Video::fromBase64(), Video::fromLocalPath(), Video::fromStoragePath(), Video::fromFileId()
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
