<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Persistence\Enums\MessageRole;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles realtime voice-to-voice session management for the sandbox.
 */
class RealtimeController
{
    /**
     * Create a new realtime session and return the client payload.
     *
     * When a conversation_id is provided, loads the message history and
     * injects it into the session instructions so the AI has full context.
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

        // Build instructions with conversation context
        $instructions = $this->buildInstructions(
            $validated['instructions'] ?? null,
            $validated['conversation_id'] ?? null,
        );

        $builder->instructions($instructions);
        $builder->withVoice($validated['voice'] ?? 'shimmer');

        if (($validated['transport'] ?? 'webrtc') === 'websocket') {
            $builder->viaWebSocket();
        }

        if (($validated['turn_detection'] ?? 'server_vad') === 'manual') {
            $builder->withManualTurnDetection();
        }

        $session = $builder->createSession();

        return response()->json($session->toClientPayload());
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
