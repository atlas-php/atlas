<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Requests;

/**
 * Request object for audio generation and transcription.
 */
class AudioRequest
{
    /**
     * @param  array<int, mixed>  $media
     * @param  array<string, mixed>|null  $voiceClone
     * @param  array<string, mixed>  $providerOptions
     */
    public function __construct(
        public readonly string $model,
        public readonly ?string $instructions,
        public readonly array $media,
        public readonly ?string $voice,
        public readonly ?float $speed,
        public readonly ?string $language,
        public readonly ?int $duration,
        public readonly ?string $format,
        public readonly ?array $voiceClone,
        public readonly array $providerOptions = [],
    ) {}
}
