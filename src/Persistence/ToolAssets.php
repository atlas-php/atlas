<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence;

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Services\ExecutionService;
use Atlasphp\Atlas\Persistence\Support\MimeTypeMap;
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

        $disk = app(AtlasConfig::class)->storageDisk ?? config('filesystems.default', 'local');
        $prefix = app(AtlasConfig::class)->storagePrefix;
        $visibility = 'private';

        $extension = MimeTypeMap::toExtension($data['mime_type'] ?? null);
        $filename = Str::random(40).'.'.$extension;
        $path = $prefix.'/assets/'.$filename;

        Storage::disk($disk)->put($path, $content, $visibility);

        /** @var class-string<Asset> $assetModel */
        $assetModel = app(AtlasConfig::class)->model('asset', Asset::class);

        // Derive owner from execution's conversation — canonical source
        $conversation = $execution?->conversation;

        $asset = $assetModel::create([
            'type' => $data['type'] ?? 'file',
            'mime_type' => $data['mime_type'] ?? null,
            'filename' => $filename,
            'path' => $path,
            'disk' => $disk,
            'size_bytes' => strlen($content),
            'description' => $data['description'] ?? null,
            'agent' => $execution?->agent,
            'execution_id' => $execution?->id,
            'tool_call_id' => $toolCall?->id,
            'owner_type' => $conversation?->owner_type,
            'owner_id' => $conversation?->owner_id,
            'metadata' => $data['metadata'] ?? null,
        ]);

        return $asset;
    }

    /**
     * Get the last asset stored during the current execution.
     *
     * Available immediately after an Atlas media call (image/audio/video)
     * completes inside a tool. TrackProviderCall stores the asset and
     * makes it available via the ExecutionService.
     */
    public static function lastStored(): ?Asset
    {
        return app(ExecutionService::class)->getLastAsset();
    }
}
