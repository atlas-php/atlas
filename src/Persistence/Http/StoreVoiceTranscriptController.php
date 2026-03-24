<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Http;

use Atlasphp\Atlas\Persistence\Models\VoiceCall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Saves voice transcript turns to the VoiceCall record.
 *
 * Called by the browser as a checkpoint (on each response.done) and on session
 * close. The full transcript array is replaced atomically — no appending, no
 * duplicates. The VoiceCall record was created when the session started.
 */
class StoreVoiceTranscriptController
{
    public function __invoke(Request $request, string $sessionId): JsonResponse
    {
        $validated = $request->validate([
            'turns' => 'required|array|min:1',
            'turns.*.role' => 'required|string|in:user,assistant',
            'turns.*.content' => 'required|string|min:1',
        ]);

        /** @var class-string<VoiceCall> $model */
        $model = config('atlas.persistence.models.voice_call', VoiceCall::class);

        if (! config('atlas.persistence.enabled')) {
            return response()->json(['saved' => false, 'reason' => 'persistence_disabled']);
        }

        $voiceCall = $model::where('voice_session_id', $sessionId)->first();

        if ($voiceCall === null) {
            return response()->json(['error' => 'Voice call not found'], 404);
        }

        $voiceCall->saveTranscript($validated['turns']);

        return response()->json(['saved' => true]);
    }
}
