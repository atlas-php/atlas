<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Enums;

/**
 * Enum representing supported media source types.
 *
 * Defines how the media data should be interpreted and loaded.
 */
enum MediaSource: string
{
    case Url = 'url';
    case Base64 = 'base64';
    case FileId = 'file_id';
    case LocalPath = 'local_path';
    case StoragePath = 'storage_path';
}
