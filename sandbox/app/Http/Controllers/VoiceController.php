<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Atlasphp\Atlas\Facades\Atlas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles voice session management for the sandbox.
 *
 * Uses agent()->asVoice() which resolves the agent's tools, instructions,
 * and voice config, creates a browser-direct session with an ephemeral token,
 * and registers tools for server-side execution.
 */
class VoiceController
{
    public function createSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => 'sometimes|integer',
        ]);

        $user = User::findOrFail(1);

        $builder = Atlas::agent('assistant')
            ->for($user)
            ->asUser($user);

        if (! empty($validated['conversation_id'])) {
            $builder->forConversation($validated['conversation_id']);
        }

        $session = $builder->asVoice();

        return response()->json($session->toClientPayload());
    }
}
