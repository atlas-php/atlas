<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\MessageResource;
use App\Models\User;
use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Input\Image;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Persistence\Enums\AssetType;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\Message;
use Atlasphp\Atlas\Persistence\Models\MessageAttachment;
use Atlasphp\Atlas\Persistence\Services\ConversationService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
     * Pre-resolves the conversation so the UI always has an ID to subscribe to
     * for broadcasting. Dispatches execution to the queue asynchronously.
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string',
            'conversation_id' => 'nullable|integer',
            'attachments' => 'nullable|array',
            'attachments.*.base64' => 'required_with:attachments|string',
            'attachments.*.mime' => 'required_with:attachments|string',
            'attachments.*.name' => 'required_with:attachments|string',
        ]);

        $user = User::findOrFail(1);
        $conversationId = $request->integer('conversation_id');

        // Create a new conversation or continue an existing one
        if ($conversationId <= 0) {
            $conversation = Conversation::create([
                'owner_type' => $user->getMorphClass(),
                'owner_id' => $user->getKey(),
                'agent' => 'assistant',
                'title' => $this->generateTitle($request->string('message')->toString()),
            ]);
            $conversationId = $conversation->id;
        }

        $conversation = Conversation::findOrFail($conversationId);
        $rawAttachments = $request->input('attachments', []);

        // Store user message in the conversation
        $userMsg = $this->conversations->addMessage(
            $conversation,
            new UserMessage(content: $request->string('message')->toString()),
            author: $user,
        );
        $userMsg->markAsRead();

        // Save attachments as assets and link to the user message
        $media = [];

        foreach ($rawAttachments as $att) {
            $isImage = str_starts_with($att['mime'], 'image/');
            $content = base64_decode($att['base64']);
            $ext = $this->mimeToExtension($att['mime']);
            $filename = Str::random(40).'.'.$ext;
            $disk = config('atlas.storage.disk') ?? config('filesystems.default');
            $path = (config('atlas.storage.prefix', 'atlas')).'/uploads/'.$filename;

            Storage::disk($disk)->put($path, $content);

            $asset = Asset::create([
                'type' => $isImage ? AssetType::Image : AssetType::Document,
                'mime_type' => $att['mime'],
                'filename' => $filename,
                'original_filename' => $att['name'],
                'path' => $path,
                'disk' => $disk,
                'size_bytes' => strlen($content),
                'content_hash' => hash('sha256', $content),
                'author_type' => $user->getMorphClass(),
                'author_id' => $user->getKey(),
            ]);

            MessageAttachment::create([
                'message_id' => $userMsg->id,
                'asset_id' => $asset->id,
            ]);

            if ($isImage) {
                $media[] = Image::fromBase64($att['base64'], $att['mime']);
            }
        }

        // Auto-set title from first message if blank
        if ($conversation->title === null || $conversation->title === '') {
            $conversation->update([
                'title' => $this->generateTitle($request->string('message')->toString()),
            ]);
        }

        // Dispatch in respond mode — user message is already stored
        $agentRequest = Atlas::agent('assistant')
            ->for($user)
            ->message($request->string('message')->toString(), $media)
            ->queue()
            ->forConversation($conversationId)
            ->respond()
            ->broadcastOn(new Channel('conversation.'.$conversationId));

        $pending = $agentRequest->asText();

        return new JsonResponse([
            'execution_id' => $pending->executionId,
            'conversation_id' => $conversationId,
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
     *
     * Reconstructs media from saved assets on the parent user message
     * so the AI sees the same images on retry.
     */
    public function retry(int $conversationId): JsonResponse
    {
        $user = User::findOrFail(1);
        $conversation = Conversation::findOrFail($conversationId);

        // Find the last assistant message to get its parent (user message)
        $lastAssistant = Message::where('conversation_id', $conversationId)
            ->where('is_active', true)
            ->where('role', 'assistant')
            ->latest('sequence')
            ->first();

        // Rebuild media from the parent user message's assets
        $media = [];

        if ($lastAssistant?->parent_id) {
            $parentMsg = Message::with('attachments.asset')->find($lastAssistant->parent_id);

            if ($parentMsg) {
                foreach ($parentMsg->attachments as $att) {
                    if ($att->asset->type === AssetType::Image) {
                        $disk = $att->asset->disk ?? config('filesystems.default');
                        $media[] = Image::fromStorage($att->asset->path, $disk);
                    }
                }
            }
        }

        $agentRequest = Atlas::agent('assistant')
            ->for($user)
            ->forConversation($conversationId)
            ->retry()
            ->queue()
            ->broadcastOn(new Channel('conversation.'.$conversationId));

        if ($media !== []) {
            // Re-send the parent user message text with its media
            $parentContent = Message::find($lastAssistant->parent_id)?->content ?? '';
            $agentRequest->message($parentContent, $media);
        }

        $pending = $agentRequest->asText();

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
            ->with(['attachments.asset']);

        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }

        return $query->orderByDesc('sequence')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (Message $msg) => MessageResource::make($msg))
            ->all();
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

    /**
     * Resolve a file extension from a MIME type.
     */
    protected function mimeToExtension(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'text/markdown' => 'md',
            'text/csv' => 'csv',
            default => 'bin',
        };
    }
}
