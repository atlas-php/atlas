<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Input;

use Illuminate\Http\UploadedFile;

/**
 * Represents a video input from various sources.
 */
class Video extends Input
{
    public static function fromUrl(string $url): self
    {
        $input = new self;
        $input->url = $url;

        return $input;
    }

    public static function fromPath(string $path): self
    {
        $input = new self;
        $input->path = $path;

        return $input;
    }

    public static function fromStorage(string $path, ?string $disk = null): self
    {
        $input = new self;
        $input->storagePath = $path;
        $input->disk = $disk;

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
        return $this->mime ?? 'video/mp4';
    }

    protected function defaultExtension(): string
    {
        return match ($this->mimeType()) {
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            default => 'mp4',
        };
    }
}
