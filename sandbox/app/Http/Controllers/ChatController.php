<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\MessageResource;
use App\Models\User;
use App\Support\ChatAgents;
use Atlasphp\Atlas\AgentRegistry;
use Atlasphp\Atlas\Atlas;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Input\Image;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\ConversationMessage;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Services\ConversationService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for the sandbox chat interface.
 *
 * Delegates all conversation and message management to Atlas's built-in
 * PersistConversation middleware. The controller only handles HTTP
 * concerns — validation, routing, and JSON responses.
 */
class ChatController
{
    public function __construct(
        protected readonly ConversationService $conversations,
        protected readonly AgentRegistry $agents,
    ) {}

    // ─── Chat ────────────────────────────────────────────────────

    /**
     * Send a message to the assistant agent.
     *
     * Atlas handles everything: conversation creation, user message storage,
     * assistant response storage, and history loading — via PersistConversation
     * middleware and the HasConversations trait.
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string',
            'conversation_id' => 'nullable|integer',
            'agent' => 'nullable|string|max:64',
            'attachments' => 'nullable|array',
            'attachments.*.base64' => 'required_with:attachments|string',
            'attachments.*.mime' => 'required_with:attachments|string',
            'attachments.*.name' => 'required_with:attachments|string',
        ]);

        $user = $this->demoUser();
        $conversationId = $request->integer('conversation_id');

        // Build media inputs from attachments
        $media = [];
        foreach ($request->input('attachments', []) as $att) {
            if (str_starts_with($att['mime'], 'image/')) {
                $media[] = Image::fromBase64($att['base64'], $att['mime']);
            }
        }

        // Resolve the agent: existing conversations are locked to their
        // original agent; new conversations honour the picked agent (validated)
        // and fall back to the default.
        if ($conversationId > 0) {
            // Scope to the owner so a conversation can't be hijacked by id.
            $conversation = Conversation::where('id', $conversationId)
                ->where('owner_type', $user->getMorphClass())
                ->where('owner_id', $user->getKey())
                ->firstOrFail();
            $agentKey = $conversation->agent ?? ChatAgents::default();
        } else {
            $requested = $request->string('agent')->toString();
            $agentKey = ChatAgents::isPickable($requested) ? $requested : ChatAgents::default();

            // New thread — create a fresh conversation for broadcasting.
            // PersistConversation middleware will use this conversation
            // since forConversation() is set.
            $conversation = Conversation::create([
                'owner_type' => $user->getMorphClass(),
                'owner_id' => $user->getKey(),
                'agent' => $agentKey,
            ]);
            $conversationId = $conversation->id;
        }

        // Atlas handles everything — conversation, messages, media storage,
        // history, and response persistence via PersistConversation middleware.
        $agentRequest = Atlas::agent($agentKey)
            ->for($user)
            ->message($request->string('message')->toString(), $media)
            ->queue()
            ->withQueueDelay(3)
            ->forConversation($conversationId);

        $agentRequest->broadcastOn(new Channel('conversation.'.$conversationId));

        $pending = $agentRequest->asStream();

        return new JsonResponse([
            'execution_id' => $pending->executionId,
            'conversation_id' => $conversationId,
        ], 202);
    }

    /**
     * List the chat-selectable agents for the picker.
     */
    public function agents(): JsonResponse
    {
        $default = ChatAgents::default();
        $roster = ChatAgents::roster();

        $agents = [];
        foreach ($roster as $key => $meta) {
            if (! $this->agents->has($key)) {
                continue;
            }

            $agent = $this->agents->resolve($key);
            $provider = $agent->provider();

            $agents[] = [
                'key' => $key,
                'name' => $agent->name(),
                'description' => $agent->description(),
                'provider' => $provider instanceof Provider ? $provider->value : $provider,
                'model' => $agent->model(),
                'icon' => $meta['icon'],
                'kind' => $meta['kind'],
                'default' => $key === $default,
            ];
        }

        return new JsonResponse([
            'agents' => $agents,
            'default' => $default,
        ]);
    }

    // ─── Conversations ───────────────────────────────────────────

    /**
     * List all conversations for the sidebar.
     */
    public function index(): JsonResponse
    {
        $user = $this->demoUser();

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
        $user = $this->demoUser();
        $conversation = Conversation::where('id', $conversationId)
            ->where('owner_type', $user->getMorphClass())
            ->where('owner_id', $user->getKey())
            ->firstOrFail();
        $agentKey = $conversation->agent ?? ChatAgents::default();

        $pending = Atlas::agent($agentKey)
            ->for($user)
            ->forConversation($conversationId)
            ->retry()
            ->queue()
            ->broadcastOn(new Channel('conversation.'.$conversationId))
            ->asStream();

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
        $message = ConversationMessage::findOrFail($messageId);

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
        $message = ConversationMessage::findOrFail($messageId);

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
            'usage' => $execution->usage(),
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
     * Resolve the sandbox's single demo user, creating it on first use.
     *
     * The sandbox has no auth — every conversation is owned by one demo
     * user. Self-healing so a freshly migrated database never 404s before
     * `sandbox:fresh` has been run.
     */
    protected function demoUser(): User
    {
        return User::firstOrCreate(
            ['email' => 'sandbox@atlas.test'],
            ['name' => 'Sandbox User'],
        );
    }

    /**
     * Load messages with cursor-based pagination.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function loadMessages(Conversation $conversation, int $limit, ?int $beforeId = null): array
    {
        $query = ConversationMessage::where('conversation_id', $conversation->id)
            ->where('is_active', true)
            ->with(['assets.asset']);

        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }

        $messages = $query->orderByDesc('sequence')
            ->limit($limit)
            ->get();

        // Mark unread assistant messages as read when loaded by the UI
        $unread = $messages->filter(fn (ConversationMessage $msg) => $msg->isFromAssistant() && $msg->isUnread());

        if ($unread->isNotEmpty()) {
            ConversationMessage::whereIn('id', $unread->pluck('id'))
                ->update(['read_at' => now()]);
        }

        return $messages
            ->reverse()
            ->values()
            ->map(fn (ConversationMessage $msg) => MessageResource::make($msg))
            ->all();
    }
}
