<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Input;

use Atlasphp\Atlas\Concerns\StoresMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Abstract base class for media input types.
 *
 * Provides common storage for different input sources (URL, path, base64, file ID,
 * Laravel Storage, UploadedFile) and persistence via the StoresMedia trait.
 */
abstract class Input
{
    use StoresMedia {
        store as traitStore;
        storeAs as traitStoreAs;
        storePublicly as traitStorePublicly;
        storePubliclyAs as traitStorePubliclyAs;
    }

    protected ?string $url = null;

    protected ?string $path = null;

    protected ?string $base64Data = null;

    protected ?string $mime = null;

    protected ?string $fileId = null;

    protected ?string $disk = null;

    protected ?string $storagePath = null;

    protected ?UploadedFile $uploadedFile = null;

    abstract public function mimeType(): string;

    // ─── Source Checks ───────────────────────────────────────────────────

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

    public function isStorage(): bool
    {
        return $this->storagePath !== null;
    }

    public function isUpload(): bool
    {
        return $this->uploadedFile !== null;
    }

    // ─── Accessors ───────────────────────────────────────────────────────

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

    public function storagePath(): ?string
    {
        return $this->storagePath;
    }

    public function storageDisk(): ?string
    {
        return $this->disk;
    }

    // ─── Storage (overrides trait to update internal state) ──────────────

    /**
     * Store with auto-generated filename and update internal reference.
     */
    public function store(?string $disk = null): string
    {
        return $this->storeAs($this->generatePath(), $disk);
    }

    /**
     * Store at a specific path and update internal reference.
     *
     * After calling this, the Input references the stored copy. Subsequent
     * calls to contents()/toBase64() read from storage, and the MediaResolver
     * will use the persisted file instead of the original source.
     */
    public function storeAs(string $path, ?string $disk = null): string
    {
        $disk = $disk ?? $this->defaultDisk();

        Storage::disk($disk)->put($path, $this->contents());

        $this->transitionToStorage($path, $disk);

        return $path;
    }

    /**
     * Store with public visibility and update internal reference.
     */
    public function storePublicly(?string $disk = null): string
    {
        return $this->storePubliclyAs($this->generatePath(), $disk);
    }

    /**
     * Store at a specific path with public visibility and update internal reference.
     */
    public function storePubliclyAs(string $path, ?string $disk = null): string
    {
        $disk = $disk ?? $this->defaultDisk();

        Storage::disk($disk)->put($path, $this->contents(), 'public');

        $this->transitionToStorage($path, $disk);

        return $path;
    }

    /**
     * Transition the input to a storage-backed reference, clearing other sources.
     * The mime type is preserved so mimeType() still returns the correct value.
     */
    private function transitionToStorage(string $path, string $disk): void
    {
        $this->storagePath = $path;
        $this->disk = $disk;
        $this->url = null;
        $this->path = null;
        $this->base64Data = null;
        $this->uploadedFile = null;
    }

    // ─── StoresMedia Implementation ──────────────────────────────────────

    /**
     * @return array{type: string, value: string, disk?: string|null}
     */
    protected function mediaSource(): array
    {
        if ($this->base64Data !== null) {
            return ['type' => 'base64', 'value' => $this->base64Data];
        }

        if ($this->storagePath !== null) {
            return ['type' => 'storage', 'value' => $this->storagePath, 'disk' => $this->disk];
        }

        if ($this->path !== null) {
            return ['type' => 'path', 'value' => $this->path];
        }

        if ($this->uploadedFile !== null) {
            $realPath = $this->uploadedFile->getRealPath();

            if ($realPath === false) {
                throw new RuntimeException('Cannot resolve uploaded file path.');
            }

            return ['type' => 'uploaded', 'value' => $realPath];
        }

        if ($this->url !== null) {
            return ['type' => 'url', 'value' => $this->url];
        }

        if ($this->fileId !== null) {
            throw new RuntimeException(
                'Cannot read contents from a fileId-backed input — file IDs are provider-side references. '
                .'Use the provider API directly to access this file.'
            );
        }

        throw new RuntimeException('Cannot resolve media source — no source set.');
    }
}
