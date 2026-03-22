<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\Message;
use Atlasphp\Atlas\Persistence\Services\ConversationService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for the sandbox chat interface.
 *
 * Uses Atlas's built-in conversation pipeline for all message handling.
 * PersistConversation middleware owns the persistence lifecycle.
 */
class ChatController
{
    public function __construct(
        protected readonly ConversationService $conversations,
    ) {}

    // ─── Chat ────────────────────────────────────────────────────

    /**
     * Send a message to the assistant agent.
     *
     * Uses Atlas's built-in queue() to dispatch async execution.
     * PersistConversation middleware handles all message storage.
     * Broadcasting on the conversation channel notifies the UI.
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string',
            'conversation_id' => 'nullable|integer',
        ]);

        $user = User::findOrFail(1);

        $agentRequest = Atlas::agent('assistant')
            ->for($user)
            ->message($request->string('message')->toString())
            ->queue();

        $conversationId = $request->integer('conversation_id');

        if ($conversationId > 0) {
            $agentRequest->forConversation($conversationId);
        }

        $agentRequest->broadcastOn(new Channel('conversation.'.$conversationId));

        $pending = $agentRequest->asText();

        return new JsonResponse([
            'execution_id' => $pending->executionId,
        ], 202);
    }

    // ─── Conversations ───────────────────────────────────────────

    /**
     * List all conversations for the sidebar.
     */
    public function index(): JsonResponse
    {
        $user = User::findOrFail(1);

        $conversations = Conversation::where('owner_type', $user->getMorphClass())
            ->where('owner_id', $user->getKey())
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Conversation $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'agent' => $c->agent,
                'created_at' => $c->created_at,
                'updated_at' => $c->updated_at,
            ]);

        return new JsonResponse(['conversations' => $conversations]);
    }

    /**
     * Get conversation details with recent messages.
     */
    public function show(int $id): JsonResponse
    {
        $conversation = Conversation::findOrFail($id);

        $limit = 20;
        $messages = $this->loadMessages($conversation, $limit + 1);
        $hasMore = count($messages) > $limit;

        if ($hasMore) {
            $messages = array_slice($messages, -$limit);
        }

        $typing = $this->conversations->hasActiveExecution($conversation);

        return new JsonResponse([
            'id' => $conversation->id,
            'title' => $conversation->title,
            'agent' => $conversation->agent,
            'created_at' => $conversation->created_at,
            'typing' => $typing,
            'messages' => $messages,
            'has_more' => $hasMore,
        ]);
    }

    /**
     * Delete a conversation (soft delete).
     */
    public function destroy(int $id): JsonResponse
    {
        $conversation = Conversation::findOrFail($id);
        $conversation->delete();

        return new JsonResponse(null, 204);
    }

    // ─── Messages (infinite scroll) ─────────────────────────────

    /**
     * Paginated messages for infinite scroll.
     */
    public function messages(Request $request, int $conversationId): JsonResponse
    {
        $request->validate([
            'before' => 'nullable|integer',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $conversation = Conversation::findOrFail($conversationId);
        $limit = $request->integer('limit', 20);
        $before = $request->integer('before');

        $messages = $this->loadMessages($conversation, $limit + 1, $before > 0 ? $before : null);
        $hasMore = count($messages) > $limit;

        if ($hasMore) {
            $messages = array_slice($messages, -$limit);
        }

        return new JsonResponse([
            'messages' => $messages,
            'has_more' => $hasMore,
        ]);
    }

    // ─── Retry / Siblings ────────────────────────────────────────

    /**
     * Retry the last assistant response in a conversation.
     */
    public function retry(int $conversationId): JsonResponse
    {
        $user = User::findOrFail(1);

        $pending = Atlas::agent('assistant')
            ->for($user)
            ->forConversation($conversationId)
            ->retry()
            ->queue()
            ->broadcastOn(new Channel('conversation.'.$conversationId))
            ->asText();

        return new JsonResponse([
            'execution_id' => $pending->executionId,
        ], 202);
    }

    /**
     * Get sibling info for a message (for "1 of 3" retry navigation).
     */
    public function siblings(int $conversationId, int $messageId): JsonResponse
    {
        Conversation::findOrFail($conversationId);
        $message = Message::findOrFail($messageId);

        $info = $this->conversations->siblingInfo($message);

        return new JsonResponse([
            'current' => $info['current'],
            'total' => $info['total'],
        ]);
    }

    /**
     * Cycle to a specific sibling response by index.
     */
    public function cycleSibling(Request $request, int $conversationId, int $messageId): JsonResponse
    {
        $request->validate(['index' => 'required|integer|min:1']);

        $conversation = Conversation::findOrFail($conversationId);
        $message = Message::findOrFail($messageId);

        $this->conversations->cycleSibling(
            $conversation,
            $message->parent_id,
            $request->integer('index'),
        );

        return new JsonResponse(['ok' => true]);
    }

    // ─── Execution Status ────────────────────────────────────────

    /**
     * Get execution status for loading indicator.
     */
    public function executionStatus(int $id): JsonResponse
    {
        $execution = Execution::findOrFail($id);

        return new JsonResponse([
            'id' => $execution->id,
            'status' => $execution->status->label(),
            'is_active' => $execution->status->isActive(),
            'is_terminal' => $execution->status->isTerminal(),
            'started_at' => $execution->started_at,
            'completed_at' => $execution->completed_at,
            'duration_ms' => $execution->duration_ms,
            'usage' => [
                'input_tokens' => $execution->total_input_tokens,
                'output_tokens' => $execution->total_output_tokens,
            ],
            'error' => $execution->error,
        ]);
    }

    /**
     * Check if a conversation has an active execution.
     */
    public function processingStatus(int $conversationId): JsonResponse
    {
        Conversation::findOrFail($conversationId);

        $execution = Execution::where('conversation_id', $conversationId)
            ->whereIn('status', [
                ExecutionStatus::Pending,
                ExecutionStatus::Queued,
                ExecutionStatus::Processing,
            ])
            ->latest()
            ->first();

        return new JsonResponse([
            'typing' => $execution?->status === ExecutionStatus::Processing,
            'queued' => $execution !== null && $execution->status !== ExecutionStatus::Processing,
            'execution_id' => $execution?->id,
            'status' => $execution?->status->label(),
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    /**
     * Load messages with cursor-based pagination.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function loadMessages(Conversation $conversation, int $limit, ?int $beforeId = null): array
    {
        $query = Message::where('conversation_id', $conversation->id)
            ->where('is_active', true)
            ->with(['step.toolCalls', 'attachments.asset']);

        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }

        return $query->orderByDesc('sequence')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (Message $msg) => $this->formatMessage($msg))
            ->all();
    }

    /**
     * Format a message for API response.
     *
     * @return array<string, mixed>
     */
    protected function formatMessage(Message $msg): array
    {
        $data = [
            'id' => $msg->id,
            'role' => $msg->role->value,
            'status' => $msg->status->value,
            'content' => $msg->content,
            'agent' => $msg->agent,
            'parent_id' => $msg->parent_id,
            'sequence' => $msg->sequence,
            'created_at' => $msg->created_at,
        ];

        // Include tool calls from linked execution step
        if ($msg->isFromAssistant() && $msg->step !== null) {
            $toolCalls = $msg->step->toolCalls->map(fn ($tc) => [
                'id' => $tc->id,
                'name' => $tc->name,
                'arguments' => $tc->arguments,
                'result' => $tc->result,
                'status' => $tc->status->label(),
                'duration_ms' => $tc->duration_ms,
            ])->all();

            if ($toolCalls !== []) {
                $data['tool_calls'] = $toolCalls;
            }
        }

        // Include attachments with proxy URLs
        if ($msg->relationLoaded('attachments') && $msg->attachments->isNotEmpty()) {
            $data['attachments'] = $msg->attachments->map(fn ($att) => [
                'id' => $att->asset->id,
                'type' => $att->asset->type->value,
                'url' => $att->asset->url(),
                'mime_type' => $att->asset->mime_type,
                'description' => $att->asset->description,
            ])->all();
        }

        // Include sibling info for retry navigation
        if ($msg->isFromAssistant() && $msg->parent_id !== null) {
            $data['sibling_count'] = $msg->siblingCount();
            $data['sibling_index'] = $msg->siblingIndex();
        }

        return $data;
    }

    /**
     * Generate a short title from the first message.
     */
    protected function generateTitle(string $message): string
    {
        $title = str_replace("\n", ' ', $message);

        if (mb_strlen($title) > 60) {
            return mb_substr($title, 0, 57).'...';
        }

        return $title;
    }
}
