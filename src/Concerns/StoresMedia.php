<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Concerns;

use Atlasphp\Atlas\AtlasConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Provides storage capabilities for media types.
 *
 * Used by both Input types (inbound media) and Response types (outbound media)
 * to store, retrieve, and encode media content via Laravel's Storage facade.
 */
trait StoresMedia
{
    /**
     * Store with an auto-generated filename on the given disk.
     */
    public function store(?string $disk = null): string
    {
        return $this->storeAs($this->generatePath(), $disk);
    }

    /**
     * Store at a specific path on the given disk.
     */
    public function storeAs(string $path, ?string $disk = null): string
    {
        $disk = $disk ?? $this->defaultDisk();

        Storage::disk($disk)->put($path, $this->contents());

        return $path;
    }

    /**
     * Store with public visibility and an auto-generated filename.
     */
    public function storePublicly(?string $disk = null): string
    {
        return $this->storePubliclyAs($this->generatePath(), $disk);
    }

    /**
     * Store at a specific path with public visibility.
     */
    public function storePubliclyAs(string $path, ?string $disk = null): string
    {
        $disk = $disk ?? $this->defaultDisk();

        Storage::disk($disk)->put($path, $this->contents(), 'public');

        return $path;
    }

    /**
     * Get the raw binary contents.
     *
     * Resolves from whatever source is available: url, base64, path,
     * storage, uploaded file, or raw data.
     */
    public function contents(): string
    {
        $source = $this->mediaSource();

        return match ($source['type']) {
            'url' => Http::get($source['value'])->throw()->body(),
            'base64' => base64_decode($source['value']),
            'path' => $this->readFile($source['value']),
            'storage' => $this->readStorage($source['value'], $source['disk'] ?? null),
            'uploaded' => $this->readFile($source['value']),
            'raw' => $source['value'],
            default => throw new RuntimeException('Cannot resolve media contents — unknown source type.'),
        };
    }

    /**
     * Get contents as a base64-encoded string.
     */
    public function toBase64(): string
    {
        return base64_encode($this->contents());
    }

    /**
     * Each class using this trait must return the media source descriptor.
     *
     * @return array{type: string, value: string, disk?: string|null}
     */
    abstract protected function mediaSource(): array;

    /**
     * Each class must provide a default file extension.
     */
    abstract protected function defaultExtension(): string;

    protected function defaultDisk(): string
    {
        return app(AtlasConfig::class)->storageDisk ?? config('filesystems.default', 'local');
    }

    protected function generatePath(): string
    {
        $prefix = app(AtlasConfig::class)->storagePrefix;

        return $prefix.'/'.Str::uuid().'.'.$this->defaultExtension();
    }

    private function readFile(string $path): string
    {
        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new RuntimeException("Cannot read file: {$path}");
        }

        return $raw;
    }

    private function readStorage(string $path, ?string $disk): string
    {
        $raw = Storage::disk($disk)->get($path);

        if ($raw === null) {
            throw new RuntimeException("Cannot read file from storage: {$path}");
        }

        return $raw;
    }
}
