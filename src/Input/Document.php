<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Input;

use Illuminate\Http\UploadedFile;

/**
 * Represents a document input from various sources.
 */
class Document extends Input
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
        return $this->mime ?? 'application/pdf';
    }

    protected function defaultExtension(): string
    {
        return match ($this->mimeType()) {
            'text/plain' => 'txt',
            'text/markdown' => 'md',
            'text/html' => 'html',
            'text/csv' => 'csv',
            'application/json' => 'json',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            default => 'pdf',
        };
    }
}
