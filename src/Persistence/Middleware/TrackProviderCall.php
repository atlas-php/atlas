<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Middleware;

use Atlasphp\Atlas\Middleware\ProviderContext;
use Atlasphp\Atlas\Persistence\Enums\AssetType;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Services\ExecutionService;
use Atlasphp\Atlas\Persistence\Support\MimeTypeMap;
use Atlasphp\Atlas\Responses\Contracts\Storable;
use Atlasphp\Atlas\Responses\Usage;
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
        $isVoiceSession = $type === ExecutionType::Voice;
        $isDirectCall = ! $insideAgentExecution && ! $isVoiceSession;

        // ─── Execution tracking (direct calls only) ──────────────
        // Agent calls already have an execution via TrackExecution.
        // Voice sessions are tracked by AgentRequest::asVoice() — skip here.
        if ($isDirectCall) {
            $preExistingId = $context->meta['execution_id'] ?? null;

            if ($preExistingId !== null) {
                // Queued dispatch pre-creates an execution record so the UI has
                // an ID immediately. Adopt it here instead of creating a duplicate.
                $this->executionService->adoptExecution(
                    id: (int) $preExistingId,
                    provider: $context->provider,
                    model: $context->model,
                    type: $type,
                );
            } else {
                $this->executionService->createExecution(
                    provider: $context->provider,
                    model: $context->model,
                    meta: $context->meta,
                    type: $type,
                );
            }

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
            if ($isDirectCall) {
                $this->executionService->completeDirectExecution(
                    usage: $this->extractUsage($response),
                );
            }

            return $response;
        } catch (\Throwable $e) {
            if ($isDirectCall) {
                $this->executionService->failExecution($e);
            }

            throw $e;
        }
    }

    protected function isStorableResponse(mixed $response): bool
    {
        return $response instanceof Storable;
    }

    /**
     * Store response as asset with authorship and linkage.
     *
     * Links to:
     * - The current execution (always, via execution_id on asset)
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

            // Derive owner from execution's conversation — canonical source
            $conversation = $execution?->conversation;

            /** @var Asset $asset */
            $asset = $assetModel::create([
                'type' => $assetType,
                'mime_type' => $mimeType,
                'filename' => $filename,
                'path' => $path,
                'disk' => $disk,
                'size_bytes' => strlen($contents),
                'owner_type' => $conversation?->owner_type,
                'owner_id' => $conversation?->owner_id,
                'agent' => $execution?->agent,
                'execution_id' => $execution?->id,
                'tool_call_id' => $toolCall?->id,
                'metadata' => null,
            ]);

            // Track asset for immediate tool access (asset already has execution_id)
            $this->executionService->linkAsset($asset);

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
        $fromFormat = MimeTypeMap::fromFormat($format);

        if ($fromFormat !== null) {
            return $fromFormat;
        }

        return MimeTypeMap::defaultMimeType($assetType);
    }

    protected function resolveExtension(AssetType $assetType, ?string $mimeType): string
    {
        return MimeTypeMap::toExtension($mimeType, $assetType);
    }

    protected function extractUsage(mixed $response): ?Usage
    {
        if (! isset($response->usage) || ! $response->usage instanceof Usage) {
            return null;
        }

        return $response->usage;
    }
}
