<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Services;

use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\Message as AtlasMessage;
use Atlasphp\Atlas\Messages\ToolResultMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\MessageRole;
use Atlasphp\Atlas\Persistence\Enums\MessageStatus;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\Message;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Class ConversationService
 *
 * The primary API for managing conversations and messages. Keeps the Eloquent
 * queries out of middleware and traits, providing idempotent conversation
 * creation, message storage, tool-call reconstruction, retry/sibling management,
 * read tracking, and message queuing.
 */
class ConversationService
{
    /** @var class-string<Conversation> */
    private readonly string $conversationModel;

    /** @var class-string<Message> */
    private readonly string $messageModel;

    /** @var class-string<Execution> */
    private readonly string $executionModel;

    public function __construct()
    {
        $this->conversationModel = config('atlas.persistence.models.conversation', Conversation::class);
        $this->messageModel = config('atlas.persistence.models.message', Message::class);
        $this->executionModel = config('atlas.persistence.models.execution', Execution::class);
    }

    /**
     * Find or create a conversation for an owner + agent combination.
     */
    public function findOrCreate(Model $owner, ?string $agent = null): Conversation
    {
        $model = $this->conversationModel;

        return $model::firstOrCreate([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'agent' => $agent,
        ]);
    }

    /**
     * Continue an existing conversation.
     */
    public function find(int $conversationId): Conversation
    {
        $model = $this->conversationModel;

        return $model::findOrFail($conversationId);
    }

    /**
     * Load conversation messages as Atlas typed messages for provider replay.
     *
     * In a 1:1 conversation (no $forAgent), messages pass through as-is.
     *
     * In a group conversation ($forAgent specified), roles are remapped
     * so the requesting agent sees itself as the assistant and everyone
     * else as participants with name prefixes:
     *   - Messages where agent matches $forAgent → keep role=assistant
     *   - All other messages → remap to role=user with "[Name]: " prefix
     *   - System messages pass through unchanged
     *
     * Tool interactions are reconstructed from execution tables via step_id.
     * For each assistant message linked to a step with tool calls, the full
     * wire format is built: AssistantMessage(toolCalls) + ToolResultMessage[].
     *
     * @return array<int, AtlasMessage>
     */
    public function loadMessages(
        Conversation $conversation,
        int $limit = 50,
        ?string $forAgent = null,
    ): array {
        // Eager-load step + step.toolCalls for assistant messages.
        // reorder() clears the relationship's default orderBy('sequence')
        // so latest('sequence') can take the N most recent, then reverse
        // restores chronological order for the provider.
        $messages = $conversation->messages()
            ->where('is_active', true)
            ->where('status', MessageStatus::Delivered)
            ->with(['step.toolCalls'])
            ->reorder()
            ->latest('sequence')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        $result = [];

        foreach ($messages as $message) {
            $expanded = $message->isFromAssistant()
                ? $message->toAtlasMessagesWithTools()
                : [$message->toAtlasMessage()];

            foreach ($expanded as $atlasMessage) {
                $result[] = ($forAgent !== null && ! $message->isSystem())
                    ? $this->remapAtlasMessage($atlasMessage, $message, $forAgent)
                    : $atlasMessage;
            }
        }

        return $result;
    }

    /**
     * Remap an Atlas message for a specific agent's perspective in group conversations.
     *
     * The rule:
     *   - Messages authored by $forAgent → keep as-is (assistant perspective)
     *   - Other messages → remap to user with "[AuthorName]: " prefix
     *   - System messages → pass through unchanged
     *   - ToolResultMessages from agent's own steps → pass through (part of agent's execution)
     *   - ToolResultMessages from other agents' steps → remap to user context
     */
    protected function remapAtlasMessage(
        AtlasMessage $atlasMessage,
        Message $sourceMessage,
        string $forAgent,
    ): AtlasMessage {
        // System messages always pass through
        if ($sourceMessage->isSystem()) {
            return $atlasMessage;
        }

        // My own messages stay as-is (assistant + my tool results)
        if ($sourceMessage->agent === $forAgent) {
            return $atlasMessage;
        }

        // ToolResultMessages from other agents become user context
        if ($atlasMessage instanceof ToolResultMessage) {
            $name = $sourceMessage->authorName() ?? 'Unknown';

            return new UserMessage(
                content: "[{$name} tool:{$atlasMessage->toolName}]: {$atlasMessage->content}",
            );
        }

        // Other assistant/user messages become user with name prefix
        $name = $sourceMessage->authorName() ?? 'Unknown';
        $content = "[{$name}]: ".($atlasMessage->content ?? '');

        return new UserMessage(content: $content);
    }

    /**
     * Store a message in a conversation.
     *
     * Only stores user, assistant, and system messages.
     * Tool interactions are NOT stored as messages.
     */
    public function addMessage(
        Conversation $conversation,
        AtlasMessage $message,
        ?Model $author = null,
        ?string $agent = null,
        ?int $parentId = null,
        ?int $stepId = null,
    ): Message {
        $messageModel = $this->messageModel;

        return $messageModel::fromAtlasMessage(
            message: $message,
            conversationId: $conversation->id,
            sequence: $conversation->nextSequence(),
            author: $author,
            agent: $agent,
            parentId: $parentId,
            stepId: $stepId,
        );
    }

    /**
     * Store assistant messages from an execution — one per step.
     *
     * Links each assistant message to its step via step_id so that
     * loadMessages() can reconstruct tool calls from execution data.
     * Does NOT store ToolResultMessages — they live in execution_tool_calls.
     *
     * @param  array<int, array{text: string|null, step_id?: int|null}|object>  $steps  Step objects or arrays with 'text' and 'step_id'
     * @return array<int, Message>
     */
    public function addAssistantMessages(
        Conversation $conversation,
        array $steps,
        ?string $agent = null,
        ?int $parentId = null,
    ): array {
        $stored = [];
        $messageModel = $this->messageModel;
        $sequence = $conversation->nextSequence();

        foreach ($steps as $step) {
            $text = is_array($step) ? $step['text'] : $step->text;
            $stepId = is_array($step) ? ($step['step_id'] ?? null) : ($step->dbStepId ?? null);

            $stored[] = $messageModel::fromAtlasMessage(
                message: new AssistantMessage(content: $text),
                conversationId: $conversation->id,
                sequence: $sequence++,
                agent: $agent,
                parentId: $parentId,
                stepId: $stepId,
            );
        }

        return $stored;
    }

    // ─── Retry / Sibling Management ─────────────────────────────

    /**
     * Retry the last assistant response in a conversation.
     * Deactivates the current active response group and returns the
     * parent user message ID so the caller can generate a new response
     * with the same parent.
     *
     * @return int The parent user message ID to pass as parentId when storing the new response
     *
     * @throws \RuntimeException If the last response cannot be retried
     */
    public function prepareRetry(Conversation $conversation): int
    {
        return DB::transaction(function () use ($conversation): int {
            /** @var class-string<Message> $messageModel */
            $messageModel = $this->messageModel;

            // Find the last active assistant message
            $lastAssistant = $messageModel::where('conversation_id', $conversation->id)
                ->where('is_active', true)
                ->where('role', MessageRole::Assistant)
                ->latest('sequence')
                ->lockForUpdate()
                ->first();

            if ($lastAssistant === null) {
                throw new \RuntimeException('No assistant message to retry.');
            }

            if (! $lastAssistant->canRetry()) {
                throw new \RuntimeException(
                    'Can only retry the last assistant response. The conversation has continued.'
                );
            }

            $parentId = $lastAssistant->parent_id;

            if ($parentId === null) {
                throw new \RuntimeException('Cannot retry a message without a parent.');
            }

            // Deactivate the current active group (all messages with this parent that are active)
            $messageModel::where('conversation_id', $conversation->id)
                ->where('parent_id', $parentId)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            return $parentId;
        });
    }

    /**
     * Cycle to a specific sibling group by index (1-based).
     * Deactivates the current active group and activates the target group.
     *
     * @param  int  $parentId  The user message ID whose siblings to cycle
     * @param  int  $index  1-based index of the sibling group to activate
     */
    public function cycleSibling(Conversation $conversation, int $parentId, int $index): void
    {
        DB::transaction(function () use ($conversation, $parentId, $index): void {
            $messageModel = $this->messageModel;

            // Get a child message to access siblingGroups()
            $anyChild = $messageModel::where('parent_id', $parentId)->first();

            if ($anyChild === null) {
                throw new \RuntimeException('No siblings found for this message.');
            }

            $groups = $anyChild->siblingGroups();

            if ($index < 1 || $index > count($groups)) {
                throw new \RuntimeException(
                    "Sibling index {$index} out of range. Available: 1-".count($groups)
                );
            }

            // Deactivate all siblings
            $messageModel::where('conversation_id', $conversation->id)
                ->where('parent_id', $parentId)
                ->update(['is_active' => false]);

            // Activate the target group
            $targetGroup = $groups[$index - 1];
            $targetIds = $targetGroup->pluck('id');

            $messageModel::whereIn('id', $targetIds)
                ->update(['is_active' => true]);
        });
    }

    /**
     * Get sibling info for a message — for building "2 of 3" UI.
     *
     * @return array{current: int, total: int, groups: array<int, Collection<int, Message>>}
     */
    public function siblingInfo(Message $message): array
    {
        if ($message->parent_id === null) {
            return ['current' => 1, 'total' => 1, 'groups' => []];
        }

        $groups = $message->siblingGroups();
        $currentIndex = $message->siblingIndex();

        return [
            'current' => $currentIndex,
            'total' => count($groups),
            'groups' => $groups,
        ];
    }

    // ─── Read Status ────────────────────────────────────────────

    /**
     * Mark all unread messages in a conversation as read up to a given sequence.
     * Typically called by the consumer's UI when the user views the conversation.
     *
     * @param  int|null  $upToSequence  Mark messages up to this sequence. Null = all.
     */
    public function markAsRead(Conversation $conversation, ?int $upToSequence = null): int
    {
        $messageModel = $this->messageModel;

        $query = $messageModel::where('conversation_id', $conversation->id)
            ->whereNull('read_at');

        if ($upToSequence !== null) {
            $query->where('sequence', '<=', $upToSequence);
        }

        return $query->update(['read_at' => now()]);
    }

    /**
     * Get the count of unread messages in a conversation.
     */
    public function unreadCount(Conversation $conversation): int
    {
        $messageModel = $this->messageModel;

        return $messageModel::where('conversation_id', $conversation->id)
            ->where('is_active', true)
            ->whereNull('read_at')
            ->count();
    }

    // ─── Message Queuing ────────────────────────────────────────

    /**
     * Store a user message as queued — visible in UI but invisible to the agent
     * until the current execution completes.
     *
     * The optional $requestContext stores the full request state (variables, meta,
     * provider options, etc.) so that ProcessQueuedMessage can replay the request
     * identically to a direct call — not just the message text.
     *
     * @param  array<string, mixed>  $requestContext
     */
    public function queueMessage(
        Conversation $conversation,
        AtlasMessage $message,
        ?Model $author = null,
        array $requestContext = [],
    ): Message {
        $messageModel = $this->messageModel;

        return DB::transaction(function () use ($conversation, $message, $author, $requestContext, $messageModel): Message {
            $stored = $messageModel::fromAtlasMessage(
                message: $message,
                conversationId: $conversation->id,
                sequence: $conversation->nextSequence(),
                author: $author,
                status: MessageStatus::Queued,
            );

            if ($requestContext !== []) {
                $stored->update(['metadata' => $requestContext]);
            }

            return $stored;
        });
    }

    /**
     * Get the ID of the last active user message in a conversation.
     * Used by respond mode to link new responses to the triggering user message.
     */
    public function lastUserMessageId(Conversation $conversation): ?int
    {
        /** @var class-string<Message> $messageModel */
        $messageModel = $this->messageModel;

        return $messageModel::where('conversation_id', $conversation->id)
            ->where('role', MessageRole::User)
            ->where('is_active', true)
            ->latest('sequence')
            ->value('id');
    }

    /**
     * Check if a conversation has an execution currently processing.
     */
    public function hasActiveExecution(Conversation $conversation): bool
    {
        $executionModel = $this->executionModel;

        return $executionModel::where('conversation_id', $conversation->id)
            ->where('status', ExecutionStatus::Processing)
            ->exists();
    }

    /**
     * Get the next queued message in a conversation (by sequence order).
     * Returns null if no queued messages exist.
     */
    public function nextQueuedMessage(Conversation $conversation): ?Message
    {
        $messageModel = $this->messageModel;

        return $messageModel::where('conversation_id', $conversation->id)
            ->where('status', MessageStatus::Queued)
            ->where('is_active', true)
            ->orderBy('sequence')
            ->first();
    }

    /**
     * Deliver the next queued message — transition from queued to delivered.
     * Returns the message if one was delivered, null if queue is empty.
     */
    public function deliverNextQueued(Conversation $conversation): ?Message
    {
        $message = $this->nextQueuedMessage($conversation);

        if ($message === null) {
            return null;
        }

        $message->markDelivered();

        return $message;
    }
}
