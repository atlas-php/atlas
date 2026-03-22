<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Middleware;

use Atlasphp\Atlas\Middleware\ProviderContext;
use Atlasphp\Atlas\Persistence\Enums\AssetType;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Services\ExecutionService;
use Atlasphp\Atlas\Providers\Contracts\HasContents;
use Closure;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Class TrackProviderCall
 *
 * Provider-layer middleware that handles two concerns:
 *
 * 1. **Execution tracking for direct calls** — when no agent execution is active,
 *    creates a standalone execution record. Agent calls already have their own
 *    execution via TrackExecution middleware.
 *
 * 2. **Asset storage for all file-producing responses** — regardless of whether
 *    this is a direct call or a tool inside an agent. When an agent tool calls
 *    Atlas::image(), the image is stored and linked to the current tool call.
 *    When called directly, the image is linked to the standalone execution.
 */
class TrackProviderCall
{
    public function __construct(
        protected readonly ExecutionService $executionService,
    ) {}

    public function handle(ProviderContext $context, Closure $next): mixed
    {
        $type = ExecutionType::fromDriverMethod($context->method);
        $insideAgentExecution = $this->executionService->hasActiveExecution();

        // ─── Execution tracking (direct calls only) ──────────────
        // Agent calls already have an execution via TrackExecution.
        if (! $insideAgentExecution) {
            $this->executionService->createExecution(
                provider: $context->provider,
                model: $context->model,
                meta: $context->meta,
                type: $type,
            );
            $this->executionService->beginExecution();
        }

        try {
            $response = $next($context);

            // ─── Asset storage (always, for file-producing responses) ─
            if ($type->producesFile()
                && config('atlas.persistence.auto_store_assets', true)
                && $this->isStorableResponse($response)
            ) {
                $this->storeAsset($response, $type, $context);
            }

            // ─── Complete standalone execution with tokens ────────────
            if (! $insideAgentExecution) {
                $this->executionService->completeDirectExecution(
                    inputTokens: $this->extractInputTokens($response),
                    outputTokens: $this->extractOutputTokens($response),
                );
            }

            return $response;
        } catch (\Throwable $e) {
            if (! $insideAgentExecution) {
                $this->executionService->failExecution($e);
            }

            throw $e;
        }
    }

    protected function isStorableResponse(mixed $response): bool
    {
        return $response instanceof HasContents;
    }

    /**
     * Store response as asset with authorship and linkage.
     *
     * Links to:
     * - The current execution (always, via execution_id on asset + asset_id on execution)
     * - The current tool call (when inside an agent tool, via asset_id on tool call)
     */
    protected function storeAsset(mixed $response, ExecutionType $type, ProviderContext $context): void
    {
        $assetType = $type->assetType();

        if ($assetType === null) {
            return;
        }

        $execution = $this->executionService->getExecution();
        $toolCall = $this->executionService->getCurrentToolCall();

        try {
            $contents = $response->contents();
            $mimeType = $this->resolveMimeType($response, $assetType);
            $disk = config('atlas.storage.disk') ?? config('filesystems.default', 'local');
            $prefix = config('atlas.storage.prefix', 'atlas');
            $visibility = config('atlas.storage.visibility', 'private');
            $extension = $this->resolveExtension($assetType, $mimeType);
            $filename = Str::uuid()->toString().'.'.$extension;
            $path = $prefix.'/assets/'.$filename;

            Storage::disk($disk)->put($path, $contents, $visibility);

            $assetModel = config('atlas.persistence.models.asset', Asset::class);

            $metadata = [
                'source' => $toolCall !== null ? 'tool_execution' : 'atlas_execution',
                'provider' => $context->provider,
                'model' => $context->model,
            ];

            if ($toolCall !== null) {
                $metadata['tool_call_id'] = $toolCall->tool_call_id;
                $metadata['tool_name'] = $toolCall->name;
            }

            /** @var Asset $asset */
            $asset = $assetModel::create([
                'type' => $assetType,
                'mime_type' => $mimeType,
                'filename' => $filename,
                'path' => $path,
                'disk' => $disk,
                'size_bytes' => strlen($contents),
                'content_hash' => hash('sha256', $contents),
                'author_type' => $context->meta['author_type'] ?? null,
                'author_id' => $context->meta['author_id'] ?? null,
                'agent' => $execution?->agent,
                'execution_id' => $execution?->id,
                'metadata' => $metadata,
            ]);

            // Link asset to execution (pass model for immediate tool access)
            $this->executionService->linkAsset($asset->id, $asset);

            // Attach asset to the response for sync callers
            if (property_exists($response, 'asset')) {
                $response->asset = $asset;
            }
        } catch (\Throwable $e) {
            // Asset storage failure should not fail the execution.
            report($e);
        }
    }

    protected function resolveMimeType(mixed $response, AssetType $assetType): ?string
    {
        // Explicit method on response takes priority
        if (method_exists($response, 'mimeType')) {
            return $response->mimeType();
        }

        // Infer from response format property
        $format = $response->format ?? null;

        if ($format !== null) {
            $map = [
                'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                'webp' => 'image/webp', 'gif' => 'image/gif',
                'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg',
                'mp4' => 'video/mp4', 'webm' => 'video/webm',
            ];

            if (isset($map[$format])) {
                return $map[$format];
            }
        }

        // Fall back to default mime type for the asset type
        return match ($assetType) {
            AssetType::Image => 'image/png',
            AssetType::Audio => 'audio/mpeg',
            AssetType::Video => 'video/mp4',
            default => null,
        };
    }

    protected function resolveExtension(AssetType $assetType, ?string $mimeType): string
    {
        if ($mimeType !== null) {
            $map = [
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                'audio/mpeg' => 'mp3',
                'audio/wav' => 'wav',
                'audio/ogg' => 'ogg',
                'video/mp4' => 'mp4',
                'video/webm' => 'webm',
            ];

            if (isset($map[$mimeType])) {
                return $map[$mimeType];
            }
        }

        return match ($assetType) {
            AssetType::Image => 'png',
            AssetType::Audio => 'mp3',
            AssetType::Video => 'mp4',
            default => 'bin',
        };
    }

    protected function extractInputTokens(mixed $response): int
    {
        return $response->usage->inputTokens ?? 0;
    }

    protected function extractOutputTokens(mixed $response): int
    {
        return $response->usage->outputTokens ?? 0;
    }
}
