<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Persistence\Enums\MessageRole;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles realtime voice-to-voice session management for the sandbox.
 *
 * Transcript persistence is handled by the Atlas package route.
 */
class RealtimeController
{
    /**
     * Create a new realtime session and return the client payload.
     *
     * Includes author and agent info in the response so the frontend
     * can pass them to the package transcript endpoint.
     */
    public function createSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => 'sometimes|string',
            'model' => 'sometimes|string',
            'voice' => 'sometimes|string',
            'transport' => 'sometimes|string|in:webrtc,websocket',
            'turn_detection' => 'sometimes|string|in:server_vad,manual',
            'instructions' => 'sometimes|string',
            'conversation_id' => 'sometimes|integer',
        ]);

        $provider = $validated['provider'] ?? config('atlas.defaults.realtime.provider', 'openai');
        $model = $validated['model'] ?? config('atlas.defaults.realtime.model');

        $builder = Atlas::realtime($provider, $model);

        $instructions = $this->buildInstructions(
            $validated['instructions'] ?? null,
            $validated['conversation_id'] ?? null,
        );

        $builder->instructions($instructions);
        $builder->withVoice($validated['voice'] ?? 'marin');
        $builder->withInputTranscription();

        if (($validated['transport'] ?? 'webrtc') === 'websocket') {
            $builder->viaWebSocket();
        }

        if (($validated['turn_detection'] ?? 'server_vad') === 'manual') {
            $builder->withManualTurnDetection();
        }

        $session = $builder->createSession();

        $transcriptEndpoint = null;
        if (config('atlas.persistence.enabled') && config('atlas.persistence.realtime_transcripts.enabled', true)) {
            $prefix = config('atlas.persistence.realtime_transcripts.route_prefix', 'atlas');
            $transcriptEndpoint = url("/{$prefix}/realtime/{$session->sessionId}/transcript");
        }

        $payload = $session->toClientPayload($transcriptEndpoint);

        // Include author and agent info for transcript persistence
        $user = User::find(1);
        $payload['author_type'] = $user?->getMorphClass();
        $payload['author_id'] = $user?->getKey();
        $payload['agent'] = 'assistant';

        return response()->json($payload);
    }

    /**
     * Submit a tool call result back to an active session.
     */
    public function submitToolResult(Request $request, string $sessionId): JsonResponse
    {
        $validated = $request->validate([
            'call_id' => 'required|string',
            'result' => 'required|string',
        ]);

        return response()->json([
            'session_id' => $sessionId,
            'call_id' => $validated['call_id'],
            'status' => 'acknowledged',
        ]);
    }

    /**
     * Build session instructions, optionally including conversation history.
     */
    private function buildInstructions(?string $custom, ?int $conversationId): string
    {
        $base = $custom ?? 'You are a helpful voice assistant. Be concise and conversational.';

        if ($conversationId === null) {
            return $base;
        }

        $conversation = Conversation::with(['messages' => function ($q) {
            $q->where('is_active', true)
                ->whereIn('role', [MessageRole::User, MessageRole::Assistant])
                ->whereNotNull('content')
                ->orderBy('sequence')
                ->limit(50);
        }])->find($conversationId);

        if ($conversation === null || $conversation->messages->isEmpty()) {
            return $base;
        }

        $history = $conversation->messages->map(function ($msg) {
            $role = $msg->role === MessageRole::User ? 'User' : 'Assistant';

            return "{$role}: {$msg->content}";
        })->implode("\n");

        return "{$base}\n\nHere is the conversation so far. The user is now switching to voice. Continue from where it left off and reference past messages when relevant:\n\n{$history}";
    }
}
