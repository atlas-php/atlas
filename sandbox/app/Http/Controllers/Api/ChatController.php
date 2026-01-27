<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Message;
use App\Models\Thread;
use Atlasphp\Atlas\Agents\Contracts\AgentRegistryContract;
use Atlasphp\Atlas\Atlas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

/**
 * API controller for chat functionality.
 *
 * Provides JSON API endpoints for managing chat threads and messages
 * with AI agents.
 */
class ChatController extends Controller
{
    /**
     * List available agents.
     */
    public function agents(AgentRegistryContract $registry): JsonResponse
    {
        $agents = [];

        foreach ($registry->keys() as $key) {
            $agent = $registry->get($key);
            $agents[] = [
                'key' => $key,
                'provider' => $agent->provider(),
                'model' => $agent->model(),
                'description' => $agent->description(),
            ];
        }

        return response()->json(['agents' => $agents]);
    }

    /**
     * List all threads.
     */
    public function index(): JsonResponse
    {
        $threads = Thread::with(['messages' => fn ($q) => $q->latest()->limit(1)])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Thread $thread) => [
                'id' => $thread->id,
                'agent_key' => $thread->agent_key,
                'title' => $thread->title,
                'last_message' => $thread->messages->first()?->content,
                'message_count' => $thread->messages()->count(),
                'created_at' => $thread->created_at?->toIso8601String(),
                'updated_at' => $thread->updated_at?->toIso8601String(),
            ]);

        return response()->json(['threads' => $threads]);
    }

    /**
     * Create a new thread and optionally send the first message.
     */
    public function store(Request $request, AgentRegistryContract $registry): JsonResponse
    {
        $agentKey = $request->input('agent_key', 'general-assistant');
        $content = $request->input('message');

        if (! $registry->has($agentKey)) {
            return response()->json(['error' => 'Agent not found'], 404);
        }

        $thread = Thread::create([
            'agent_key' => $agentKey,
            'title' => null,
        ]);

        if ($content) {
            return $this->sendMessage($thread, $content);
        }

        return response()->json([
            'thread' => $this->formatThread($thread),
        ], 201);
    }

    /**
     * Get a thread with all messages.
     */
    public function show(int $id): JsonResponse
    {
        $thread = Thread::with('messages')->find($id);

        if (! $thread) {
            return response()->json(['error' => 'Thread not found'], 404);
        }

        return response()->json([
            'thread' => $this->formatThread($thread),
        ]);
    }

    /**
     * Delete a thread.
     */
    public function destroy(int $id): JsonResponse
    {
        $thread = Thread::find($id);

        if (! $thread) {
            return response()->json(['error' => 'Thread not found'], 404);
        }

        $thread->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Send a message to a thread.
     */
    public function message(Request $request, int $id): JsonResponse
    {
        $thread = Thread::find($id);

        if (! $thread) {
            return response()->json(['error' => 'Thread not found'], 404);
        }

        $content = $request->input('message');

        if (! $content) {
            return response()->json(['error' => 'Message content required'], 400);
        }

        return $this->sendMessage($thread, $content);
    }

    /**
     * Send a message and get the AI response.
     */
    protected function sendMessage(Thread $thread, string $content): JsonResponse
    {
        // Create user message
        $userMessage = $thread->messages()->create([
            'role' => 'user',
            'content' => $content,
            'status' => 'completed',
        ]);

        // Create assistant message in processing state
        $assistantMessage = $thread->messages()->create([
            'role' => 'assistant',
            'content' => '',
            'status' => 'processing',
        ]);

        // Build message history for context
        $history = $thread->messages()
            ->where('id', '<', $assistantMessage->id)
            ->where('status', 'completed')
            ->get()
            ->map(fn (Message $msg) => [
                'role' => $msg->role,
                'content' => $msg->content,
            ])
            ->toArray();

        // Remove the last user message from history (it's the current one)
        array_pop($history);

        try {
            $response = Atlas::agent($thread->agent_key)
                ->withMessages($history)
                ->chat($content);

            $responseText = $response->text ?? '';

            $assistantMessage->update([
                'content' => $responseText,
                'status' => 'completed',
            ]);

            // Generate title from first message if not set
            if (! $thread->title && $thread->messages()->count() <= 2) {
                $thread->update([
                    'title' => $this->generateTitle($content),
                ]);
            }

            $thread->touch();

        } catch (Throwable $e) {
            $assistantMessage->update([
                'content' => 'Error: '.$e->getMessage(),
                'status' => 'failed',
            ]);
        }

        return response()->json([
            'thread' => $this->formatThread($thread->fresh(['messages'])),
        ]);
    }

    /**
     * Format a thread for JSON response.
     *
     * @return array<string, mixed>
     */
    protected function formatThread(Thread $thread): array
    {
        return [
            'id' => $thread->id,
            'agent_key' => $thread->agent_key,
            'title' => $thread->title,
            'created_at' => $thread->created_at?->toIso8601String(),
            'updated_at' => $thread->updated_at?->toIso8601String(),
            'messages' => $thread->messages->map(fn (Message $msg) => [
                'id' => $msg->id,
                'role' => $msg->role,
                'content' => $msg->content,
                'status' => $msg->status,
                'created_at' => $msg->created_at?->toIso8601String(),
            ])->toArray(),
        ];
    }

    /**
     * Generate a short title from the first message.
     */
    protected function generateTitle(string $content): string
    {
        $title = mb_substr($content, 0, 50);

        if (mb_strlen($content) > 50) {
            $title .= '...';
        }

        return $title;
    }
}
