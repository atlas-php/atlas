<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Atlasphp\Atlas\Persistence\Models\Asset;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Proxy controller for serving stored assets from disk.
 *
 * Provider URLs (xAI, ElevenLabs, etc.) are temporary. Assets are stored
 * to local disk by TrackProviderCall middleware. This endpoint serves
 * them back to the UI by reading from storage.
 */
class AssetController
{
    /**
     * Stream an asset file from storage.
     */
    public function show(int $id): StreamedResponse
    {
        $asset = Asset::findOrFail($id);

        $disk = $asset->disk ?? config('filesystems.default', 'local');

        abort_unless(Storage::disk($disk)->exists($asset->path), 404);

        return Storage::disk($disk)->response(
            $asset->path,
            $asset->filename,
            ['Content-Type' => $asset->mime_type ?? 'application/octet-stream'],
        );
    }
}
