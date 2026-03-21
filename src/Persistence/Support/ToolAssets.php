<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Support;

use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Services\ExecutionService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Helper for tools to store non-Atlas-generated files as tracked assets.
 *
 * Atlas modality calls (Atlas::image(), Atlas::audio(), etc.) automatically
 * store their output as assets via TrackProviderCall middleware — no manual
 * intervention needed. This helper is for files that tools generate WITHOUT
 * going through an Atlas modality: CSV exports, PDF reports, custom files.
 *
 * Usage inside a tool's handle():
 *
 *   $csv = generateReport($data);
 *   $asset = ToolAssets::store($csv, [
 *       'type' => 'document',
 *       'mime_type' => 'text/csv',
 *       'description' => 'Monthly sales report',
 *   ]);
 *   return "Report generated: {$asset->path}";
 */
class ToolAssets
{
    /**
     * Store raw content as an asset linked to the current tool execution.
     *
     * Automatically links the asset to the current execution and tool call
     * when called inside an agent's tool handle() method.
     *
     * @param  string  $content  Raw file content
     * @param  array<string, mixed>  $data  Asset data (type, mime_type, description, etc.)
     */
    public static function store(string $content, array $data = []): Asset
    {
        $executionService = app(ExecutionService::class);
        $execution = $executionService->getExecution();
        $toolCall = $executionService->getCurrentToolCall();

        $disk = config('atlas.storage.disk') ?? config('filesystems.default');
        $prefix = config('atlas.storage.prefix', 'atlas');
        $visibility = config('atlas.storage.visibility', 'private');

        $hash = hash('sha256', $content);
        $extension = static::resolveExtension($data['mime_type'] ?? null);
        $filename = Str::random(40).'.'.$extension;
        $path = $prefix.'/tools/'.$filename;

        Storage::disk($disk)->put($path, $content, $visibility);

        /** @var class-string<Asset> $assetModel */
        $assetModel = config('atlas.persistence.models.asset', Asset::class);

        $metadata = array_merge($data['metadata'] ?? [], [
            'source' => 'tool_execution',
        ]);

        if ($toolCall !== null) {
            $metadata['tool_call_id'] = $toolCall->id;
            $metadata['tool_name'] = $toolCall->name;
        }

        $asset = $assetModel::create([
            'type' => $data['type'] ?? 'file',
            'mime_type' => $data['mime_type'] ?? null,
            'filename' => $filename,
            'original_filename' => $data['original_filename'] ?? null,
            'path' => $path,
            'disk' => $disk,
            'size_bytes' => strlen($content),
            'content_hash' => $hash,
            'description' => $data['description'] ?? null,
            'agent' => $execution?->agent,
            'execution_id' => $execution?->id,
            'metadata' => $metadata,
        ]);

        return $asset;
    }

    /**
     * Resolve a file extension from a MIME type.
     */
    protected static function resolveExtension(?string $mimeType): string
    {
        return match ($mimeType) {
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
            default => 'bin',
        };
    }
}
