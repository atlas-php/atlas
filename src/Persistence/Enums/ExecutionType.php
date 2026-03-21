<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Enums;

/**
 * Type classification for executions — maps 1:1 to Driver dispatch methods.
 */
enum ExecutionType: string
{
    case Text = 'text';
    case Structured = 'structured';
    case Stream = 'stream';
    case Image = 'image';
    case ImageToText = 'image_to_text';
    case Audio = 'audio';
    case AudioToText = 'audio_to_text';
    case Video = 'video';
    case VideoToText = 'video_to_text';
    case Embed = 'embed';
    case Moderate = 'moderate';
    case Rerank = 'rerank';

    /**
     * Whether this execution type produces a file output.
     */
    public function producesFile(): bool
    {
        return in_array($this, [self::Image, self::Audio, self::Video]);
    }

    /**
     * Map to AssetType for storage.
     */
    public function assetType(): ?AssetType
    {
        return match ($this) {
            self::Image => AssetType::Image,
            self::Audio => AssetType::Audio,
            self::Video => AssetType::Video,
            default => null,
        };
    }

    /**
     * Resolve from Driver::dispatch() method name.
     */
    public static function fromDriverMethod(string $method): self
    {
        return match ($method) {
            'text' => self::Text,
            'structured' => self::Structured,
            'stream' => self::Stream,
            'image' => self::Image,
            'imageToText' => self::ImageToText,
            'audio' => self::Audio,
            'audioToText' => self::AudioToText,
            'video' => self::Video,
            'videoToText' => self::VideoToText,
            'embed' => self::Embed,
            'moderate' => self::Moderate,
            'rerank' => self::Rerank,
            default => self::Text,
        };
    }
}
