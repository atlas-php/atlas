<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Models;

use Atlasphp\Atlas\Database\Factories\MessageFactory;
use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\Message as AtlasMessage;
use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Messages\ToolResultMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Persistence\Concerns\HasAtlasTable;
use Atlasphp\Atlas\Persistence\Concerns\HasOwner;
use Atlasphp\Atlas\Persistence\Enums\MessageRole;
use Atlasphp\Atlas\Persistence\Enums\MessageStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Class Message
 *
 * Represents a single message within a conversation. Supports user, assistant, and system roles
 * with polymorphic ownership, read/queue status tracking, sibling/retry grouping, and conversion
 * to/from Atlas typed messages for provider replay.
 *
 * @property int $id
 * @property int $conversation_id
 * @property int|null $parent_id
 * @property int|null $step_id
 * @property MessageRole $role
 * @property MessageStatus $status
 * @property string|null $owner_type
 * @property int|null $owner_id
 * @property string|null $agent
 * @property string|null $content
 * @property int $sequence
 * @property bool $is_active
 * @property Carbon|null $read_at
 * @property array<mixed>|null $embedding
 * @property Carbon|null $embedding_at
 * @property array<mixed>|null $metadata
 */
class Message extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasAtlasTable, HasFactory, HasOwner;

    protected static function newFactory(): MessageFactory
    {
        return MessageFactory::new();
    }

    protected $table = 'messages';

    protected $fillable = [
        'conversation_id',
        'parent_id',
        'step_id',
        'role',
        'status',
        'owner_type',
        'owner_id',
        'agent',
        'content',
        'sequence',
        'is_active',
        'read_at',
        'embedding',
        'embedding_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'role' => MessageRole::class,
            'status' => MessageStatus::class,
            'sequence' => 'integer',
            'is_active' => 'boolean',
            'read_at' => 'datetime',
            'embedding' => 'array',
            'embedding_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    // ─── Relationships ──────────────────────────────────────────

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        /** @var class-string<Conversation> $model */
        $model = config('atlas.persistence.models.conversation', Conversation::class);

        return $this->belongsTo($model);
    }

    /**
     * The execution step that produced this assistant message.
     * Used by loadMessages() to reconstruct tool calls from execution data.
     * Null for user/system messages.
     *
     * @return BelongsTo<ExecutionStep, $this>
     */
    public function step(): BelongsTo
    {
        /** @var class-string<ExecutionStep> $model */
        $model = config('atlas.persistence.models.execution_step', ExecutionStep::class);

        return $this->belongsTo($model, 'step_id');
    }

    /**
     * The user message that triggered this response.
     * All retries share the same parent.
     *
     * @return BelongsTo<static, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    /**
     * All response groups that share the same parent (siblings = retries).
     *
     * @return HasMany<static, $this>
     */
    public function siblings(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id', 'parent_id')
            ->where($this->getTable().'.id', '!=', $this->id);
    }

    /**
     * All responses to this user message (when called on a user message).
     *
     * @return HasMany<static, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    /** @return HasMany<MessageAttachment, $this> */
    public function attachments(): HasMany
    {
        /** @var class-string<MessageAttachment> $model */
        $model = config('atlas.persistence.models.message_attachment', MessageAttachment::class);

        return $this->hasMany($model);
    }

    // ─── Ownership — provided by HasOwner trait ────────────────

    /**
     * Unified owner info for the UI.
     *
     * Returns a consistent structure regardless of whether the owner
     * is a human (model) or an agent (key string):
     *   ['type' => 'user', 'id' => 1, 'name' => 'Tim']
     *   ['type' => 'agent', 'key' => 'assistant', 'name' => 'assistant']
     *
     * @return array{type: string, id: int|null, key: string|null, name: string|null}
     */
    public function ownerInfo(): array
    {
        if ($this->owner_type !== null) {
            $owner = $this->owner;

            return [
                'type' => 'user',
                'id' => $this->owner_id,
                'key' => null,
                'name' => $owner->name ?? null,
            ];
        }

        if ($this->agent !== null) {
            return [
                'type' => 'agent',
                'id' => null,
                'key' => $this->agent,
                'name' => $this->agent,
            ];
        }

        return [
            'type' => 'unknown',
            'id' => null,
            'key' => null,
            'name' => null,
        ];
    }

    // ─── Conversion ─────────────────────────────────────────────

    /**
     * Convert this database message to an Atlas typed message for provider replay.
     *
     * For simple messages (user, system, assistant without tools), converts directly.
     * Does NOT handle tool reconstruction — that's loadMessages()'s job.
     * This method returns the base AssistantMessage without toolCalls.
     */
    public function toAtlasMessage(): AtlasMessage
    {
        return match ($this->role) {
            MessageRole::User => new UserMessage(
                content: $this->content ?? '',
            ),
            MessageRole::Assistant => new AssistantMessage(
                content: $this->content,
            ),
            MessageRole::System => new SystemMessage(
                content: $this->content ?? '',
            ),
        };
    }

    /**
     * Convert this assistant message to an AssistantMessage WITH tool calls
     * reconstructed from the linked execution step.
     *
     * Returns an array: [AssistantMessage, ToolResultMessage, ToolResultMessage, ...]
     * The AssistantMessage includes toolCalls from execution data.
     * Each ToolResultMessage is built from execution_tool_calls.result.
     *
     * Returns [AssistantMessage] (single-element array) if no tool calls.
     *
     * @return array<int, AtlasMessage>
     */
    public function toAtlasMessagesWithTools(): array
    {
        if (! $this->isFromAssistant()) {
            return [$this->toAtlasMessage()];
        }

        // No step link or no tool calls — simple text message
        $toolCallRecords = $this->step?->toolCalls()->orderBy('id')->get();

        if ($toolCallRecords === null || $toolCallRecords->isEmpty()) {
            return [new AssistantMessage(content: $this->content)];
        }

        // Build AssistantMessage with toolCalls array
        $toolCalls = $toolCallRecords->map(fn (ExecutionToolCall $tc) => new ToolCall(
            id: $tc->tool_call_id,
            name: $tc->name,
            arguments: $tc->arguments ?? [],
        ))->all();

        $messages = [
            new AssistantMessage(
                content: $this->content,
                toolCalls: $toolCalls,
            ),
        ];

        // Build ToolResultMessages from execution results
        foreach ($toolCallRecords as $tc) {
            $messages[] = new ToolResultMessage(
                toolCallId: $tc->tool_call_id,
                content: $tc->result ?? '',
                toolName: $tc->name,
            );
        }

        return $messages;
    }

    // ─── Query Helpers ──────────────────────────────────────────

    public function isFromUser(): bool
    {
        return $this->role === MessageRole::User;
    }

    public function isFromAssistant(): bool
    {
        return $this->role === MessageRole::Assistant;
    }

    public function isSystem(): bool
    {
        return $this->role === MessageRole::System;
    }

    /** @param Builder<static> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    // ─── Read Status ────────────────────────────────────────────

    /**
     * Mark this message as read.
     * For user messages: called when the agent starts processing (sent to provider).
     * For assistant messages: called by the consumer's UI when the human sees it.
     */
    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->update(['read_at' => now()]);
        }
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    /** @param Builder<static> $query */
    public function scopeRead(Builder $query): void
    {
        $query->whereNotNull('read_at');
    }

    /** @param Builder<static> $query */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    // ─── Queue Status ───────────────────────────────────────────

    /**
     * Transition this queued message to delivered.
     * Called when the previous execution completes and it's this message's turn.
     */
    public function markDelivered(): void
    {
        $this->update(['status' => MessageStatus::Delivered]);
    }

    public function isDelivered(): bool
    {
        return $this->status === MessageStatus::Delivered;
    }

    public function isQueued(): bool
    {
        return $this->status === MessageStatus::Queued;
    }

    /** @param Builder<static> $query */
    public function scopeDelivered(Builder $query): void
    {
        $query->where('status', MessageStatus::Delivered);
    }

    /** @param Builder<static> $query */
    public function scopeQueued(Builder $query): void
    {
        $query->where('status', MessageStatus::Queued);
    }

    // ─── Sibling / Retry Helpers ────────────────────────────────

    /**
     * Get all sibling retry groups for the same user message.
     * Each group is identified by distinct execution runs with the same parent_id.
     *
     * Groups by execution_id (via step relationship) so multi-step responses
     * stay together. Messages without a step are each treated as their own group.
     */
    /** @return array<int, Collection<int, Message>> */
    public function siblingGroups(): array
    {
        if ($this->parent_id === null) {
            return [];
        }

        $siblings = static::where('parent_id', $this->parent_id)
            ->with('step')
            ->orderBy('sequence')
            ->get();

        // Group by execution_id — messages from the same execution belong together.
        // Messages without a step_id form individual groups.
        $groups = [];
        $currentExecutionId = null;
        $current = [];

        foreach ($siblings as $message) {
            $executionId = $message->step?->execution_id;

            if ($executionId === null) {
                // No step link — standalone group
                if (! empty($current)) {
                    $groups[] = collect($current);
                    $current = [];
                    $currentExecutionId = null;
                }
                $groups[] = collect([$message]);

                continue;
            }

            if ($executionId !== $currentExecutionId && ! empty($current)) {
                $groups[] = collect($current);
                $current = [];
            }

            $current[] = $message;
            $currentExecutionId = $executionId;
        }

        if (! empty($current)) {
            $groups[] = collect($current);
        }

        return $groups;
    }

    public function siblingCount(): int
    {
        return count($this->siblingGroups());
    }

    public function siblingIndex(): int
    {
        $groups = $this->siblingGroups();

        foreach ($groups as $index => $group) {
            if ($group->contains('id', $this->id)) {
                return $index + 1;
            }
        }

        return 1;
    }

    public function canRetry(): bool
    {
        if (! $this->isFromAssistant()) {
            return false;
        }

        return ! static::where('conversation_id', $this->conversation_id)
            ->where('role', MessageRole::User)
            ->where('sequence', '>', $this->sequence)
            ->where('is_active', true)
            ->exists();
    }
}
