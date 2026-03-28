<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Input;

use Illuminate\Http\UploadedFile;

/**
 * Represents an audio input from various sources.
 */
class Audio extends Input
{
    public static function fromUrl(string $url, ?string $mimeType = null): self
    {
        $input = new self;
        $input->url = $url;
        $input->mime = $mimeType;

        return $input;
    }

    public static function fromPath(string $path, ?string $mimeType = null): self
    {
        $input = new self;
        $input->path = $path;
        $input->mime = $mimeType;

        return $input;
    }

    public static function fromStorage(string $path, ?string $disk = null, ?string $mimeType = null): self
    {
        $input = new self;
        $input->storagePath = $path;
        $input->disk = $disk;
        $input->mime = $mimeType;

        return $input;
    }

    public static function fromBase64(string $data, string $mimeType): self
    {
        $input = new self;
        $input->base64Data = $data;
        $input->mime = $mimeType;

        return $input;
    }

    public static function fromFileId(string $id): self
    {
        $input = new self;
        $input->fileId = $id;

        return $input;
    }

    public static function fromUpload(UploadedFile $file): self
    {
        $input = new self;
        $input->uploadedFile = $file;
        $input->mime = $file->getMimeType();

        return $input;
    }

    public function mimeType(): string
    {
        return $this->mime ?? 'audio/mpeg';
    }

    protected function defaultExtension(): string
    {
        return match ($this->mimeType()) {
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/flac' => 'flac',
            'audio/ogg' => 'ogg',
            'audio/webm' => 'webm',
            'audio/mp4', 'audio/m4a' => 'm4a',
            default => 'mp3',
        };
    }
}
