<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Voice\Http;

use Atlasphp\Atlas\Events\VoiceSessionClosed;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Closes a voice session and marks its execution as completed.
 *
 * Called by the browser when the WebSocket connection ends (onclose,
 * page unload, user ends call). Idempotent — safe to call multiple times.
 */
class CloseVoiceSessionController
{
    public function __invoke(Request $request, string $sessionId): Response
    {
        $execution = Execution::completeVoiceSession($sessionId);

        Cache::forget("voice:{$sessionId}:tools");

        $provider = $execution !== null ? $execution->provider : 'unknown';

        event(new VoiceSessionClosed(
            provider: $provider,
            sessionId: $sessionId,
            durationMs: $execution?->duration_ms,
        ));

        return response()->noContent();
    }
}
