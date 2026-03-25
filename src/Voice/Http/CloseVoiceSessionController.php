<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Voice\Http;

use Atlasphp\Atlas\Events\VoiceCallCompleted;
use Atlasphp\Atlas\Events\VoiceSessionClosed;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Models\VoiceCall;
use Atlasphp\Atlas\Persistence\Services\ExecutionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Closes a voice session, completes the execution, and finalizes the voice call.
 *
 * Called by the browser when the WebSocket connection ends (onclose,
 * page unload, user ends call). Accepts optional final transcript turns.
 * Idempotent — safe to call multiple times.
 */
class CloseVoiceSessionController
{
    public function __construct(
        private readonly ExecutionService $executionService,
    ) {}

    public function __invoke(Request $request, string $sessionId): Response
    {
        $voiceCall = null;
        $provider = 'unknown';

        if (config('atlas.persistence.enabled')) {
            /** @var class-string<VoiceCall> $voiceCallModel */
            $voiceCallModel = config('atlas.persistence.models.voice_call', VoiceCall::class);
            $voiceCall = $voiceCallModel::forSession($sessionId)->first();
        }

        if ($voiceCall !== null) {
            $provider = $voiceCall->provider;

            if ($voiceCall->isActive()) {
                $turns = $request->input('turns');

                if (is_array($turns) && $turns !== []) {
                    $voiceCall->markCompleted($turns);
                } else {
                    $voiceCall->markCompleted($voiceCall->transcript ?? []);
                }

                event(new VoiceCallCompleted(
                    voiceCallId: $voiceCall->id,
                    conversationId: $voiceCall->conversation_id,
                    sessionId: $sessionId,
                    transcript: $voiceCall->transcript ?? [],
                    durationMs: $voiceCall->duration_ms,
                ));
            }

            // Complete the linked execution (VoiceCall owns execution_id FK)
            $execution = $voiceCall->execution;

            if ($execution !== null && $execution->status === ExecutionStatus::Processing) {
                $this->executionService->completeVoiceExecution($execution->id);
            }
        }

        Cache::forget("voice:{$sessionId}:tools");

        event(new VoiceSessionClosed(
            provider: $provider,
            sessionId: $sessionId,
            durationMs: $voiceCall?->duration_ms,
        ));

        return response()->noContent();
    }
}
