<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Enums;

/**
 * Type classification for stored assets.
 */
enum AssetType: string
{
    case Image = 'image';
    case Audio = 'audio';
    case Video = 'video';
    case Document = 'document';
    case Text = 'text';
    case Json = 'json';
    case File = 'file';

    public function isMedia(): bool
    {
        return in_array($this, [self::Image, self::Audio, self::Video]);
    }
}
