<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Input;

/**
 * Abstract base class for media input types.
 *
 * Provides common storage for different input sources (URL, path, base64, file ID)
 * and factory methods in concrete subclasses.
 */
abstract class Input
{
    protected ?string $url = null;

    protected ?string $path = null;

    protected ?string $base64Data = null;

    protected ?string $mime = null;

    protected ?string $fileId = null;

    protected ?string $disk = null;

    abstract public function mimeType(): string;

    public function isUrl(): bool
    {
        return $this->url !== null;
    }

    public function isPath(): bool
    {
        return $this->path !== null;
    }

    public function isBase64(): bool
    {
        return $this->base64Data !== null;
    }

    public function isFileId(): bool
    {
        return $this->fileId !== null;
    }

    public function url(): ?string
    {
        return $this->url;
    }

    public function path(): ?string
    {
        return $this->path;
    }

    public function data(): ?string
    {
        return $this->base64Data;
    }

    public function fileId(): ?string
    {
        return $this->fileId;
    }

    public function disk(): ?string
    {
        return $this->disk;
    }
}
