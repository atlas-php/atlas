<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Support;

use Atlasphp\Atlas\Persistence\Enums\AssetType;

/**
 * Single source of truth for MIME type and file extension resolution.
 *
 * Used by TrackProviderCall (auto-stored assets) and ToolAssets (manual storage)
 * to ensure consistent MIME-to-extension mapping across the persistence layer.
 */
class MimeTypeMap
{
    /** @var array<string, string> */
    private const MIME_TO_EXTENSION = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
        'audio/mpeg' => 'mp3',
        'audio/wav' => 'wav',
        'audio/ogg' => 'ogg',
        'audio/flac' => 'flac',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'application/pdf' => 'pdf',
        'application/json' => 'json',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
    ];

    /** @var array<string, string> */
    private const FORMAT_TO_MIME = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
    ];

    /**
     * Resolve a file extension from a MIME type.
     *
     * Falls back to the default extension for the asset type, or 'bin' if unknown.
     */
    public static function toExtension(?string $mimeType, ?AssetType $assetType = null): string
    {
        if ($mimeType !== null && isset(self::MIME_TO_EXTENSION[$mimeType])) {
            return self::MIME_TO_EXTENSION[$mimeType];
        }

        if ($assetType !== null) {
            return match ($assetType) {
                AssetType::Image => 'png',
                AssetType::Audio => 'mp3',
                AssetType::Video => 'mp4',
                default => 'bin',
            };
        }

        return 'bin';
    }

    /**
     * Resolve a MIME type from a format string (e.g. 'png', 'mp3').
     */
    public static function fromFormat(?string $format): ?string
    {
        if ($format === null) {
            return null;
        }

        return self::FORMAT_TO_MIME[$format] ?? null;
    }

    /**
     * Resolve a default MIME type for an asset type.
     */
    public static function defaultMimeType(AssetType $assetType): ?string
    {
        return match ($assetType) {
            AssetType::Image => 'image/png',
            AssetType::Audio => 'audio/mpeg',
            AssetType::Video => 'video/mp4',
            default => null,
        };
    }
}
