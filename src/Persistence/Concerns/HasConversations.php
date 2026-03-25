<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Concerns;

use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Services\ConversationService;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait HasConversations
 *
 * Consumer-facing API for conversation management on Agent classes.
 * Provides fluent methods for setting conversation ownership,
 * response/retry modes, message limits, and conversation resolution.
 */
trait HasConversations
{
    protected ?Conversation $conversation = null;

    protected ?Model $conversationOwner = null;

    protected ?Model $messageOwner = null;

    protected ?int $conversationId = null;

    protected ?int $runtimeMessageLimit = null;

    protected bool $respondMode = false;

    protected bool $retryMode = false;

    protected ?int $retryParentId = null;

    // ─── Conversation Ownership ─────────────────────────────────

    /**
     * Set the conversation owner and optionally the message sender.
     *
     * Accepts any Eloquent model — User, Team, Account, or even
     * another Execution for agent-to-agent delegation with memory.
     *
     * When `as:` is omitted, the owner is used as the message sender.
     * Use `as:` for multi-user conversations where the sender differs
     * from the thread owner.
     */
    public function for(Model $owner, ?Model $as = null): static
    {
        $this->conversationOwner = $owner;

        if ($as !== null) {
            $this->messageOwner = $as;
        }

        return $this;
    }

    /**
     * Set the human owner of the incoming message.
     *
     * @deprecated Use for($owner, as: $user) instead.
     */
    public function asUser(Model $owner): static
    {
        $this->messageOwner = $owner;

        return $this;
    }

    /**
     * Resolve who owns the user message.
     * Explicit as: wins, otherwise falls back to conversation owner.
     */
    public function resolveOwner(): ?Model
    {
        return $this->messageOwner ?? $this->conversationOwner;
    }

    /**
     * Join an existing conversation by ID.
     * Messages will be loaded from and stored to this conversation.
     * Does not create a new conversation.
     *
     * Use for multi-agent group conversations, agent-to-agent delegation,
     * or resuming a conversation from a stored ID.
     */
    public function forConversation(int $conversationId): static
    {
        $this->conversationId = $conversationId;

        return $this;
    }

    // ─── Response Mode ──────────────────────────────────────────────

    /**
     * Respond to an existing conversation without a new user message.
     * The agent reads the conversation history and continues from
     * where things left off.
     *
     * Requires forConversation() — the agent needs to know WHICH
     * conversation to respond to.
     *
     * Used in group conversations when it's this agent's turn to speak.
     * The conversation history (with role remapping) gives the agent
     * full context of what's been said by humans and other agents.
     *
     * @throws \RuntimeException If no conversation is set
     */
    public function respond(): static
    {
        $this->respondMode = true;

        return $this;
    }

    /**
     * Whether this call is in respond mode (no user message).
     */
    public function isRespondMode(): bool
    {
        return $this->respondMode;
    }

    // ─── Retry Mode ─────────────────────────────────────────────────

    /**
     * Retry the last assistant response in the conversation.
     * Deactivates the current response and generates a new one
     * with the same parent user message.
     *
     * Requires forConversation() — the agent needs to know which
     * conversation to retry in.
     *
     * The new response becomes "N of N" in the sibling list.
     * The user can cycle between siblings in the UI.
     */
    public function retry(): static
    {
        $this->retryMode = true;

        return $this;
    }

    /**
     * Whether this call is in retry mode.
     */
    public function isRetrying(): bool
    {
        return $this->retryMode;
    }

    /**
     * Get the parent ID set by prepareRetry().
     * The middleware uses this to link the new response to the same parent.
     */
    public function getRetryParentId(): ?int
    {
        return $this->retryParentId;
    }

    /**
     * Set the retry parent ID. Called by PersistConversation middleware
     * after deactivating the current response group.
     */
    public function setRetryParentId(int $parentId): void
    {
        $this->retryParentId = $parentId;
    }

    // ─── Message Loading Configuration ──────────────────────────

    /**
     * Override message limit for this call only.
     * Takes highest priority in the resolution chain.
     */
    public function withMessageLimit(int $limit): static
    {
        $this->runtimeMessageLimit = $limit;

        return $this;
    }

    /**
     * How many messages to load from conversation history.
     * Override this on your agent class to set a per-agent default.
     *
     * Return null to fall through to config/global default.
     */
    public function messageLimit(): ?int
    {
        return null;
    }

    /**
     * Resolve the effective message limit.
     *
     * Resolution chain:
     *   1. ->withMessageLimit(20) on this call
     *   2. messageLimit() on the agent class
     *   3. config('atlas.persistence.message_limit')
     *   4. Hardcoded 50
     */
    protected function resolveMessageLimit(): int
    {
        if ($this->runtimeMessageLimit !== null) {
            return $this->runtimeMessageLimit;
        }

        $agentLimit = $this->messageLimit();

        if ($agentLimit !== null) {
            return $agentLimit;
        }

        return (int) config('atlas.persistence.message_limit', 50);
    }

    // ─── Conversation Resolution ────────────────────────────────

    /**
     * Resolve the conversation — find existing or create new.
     * Called internally before execution.
     */
    public function resolveConversation(): ?Conversation
    {
        if ($this->conversation !== null) {
            return $this->conversation;
        }

        $service = $this->conversationService();

        if ($this->conversationId !== null) {
            $this->conversation = $service->find($this->conversationId);
        } elseif ($this->conversationOwner !== null) {
            $this->conversation = $service->findOrCreate($this->conversationOwner, $this->agentKey());
        }

        return $this->conversation;
    }

    /**
     * Load conversation history as Atlas typed messages.
     * Uses the resolved message limit from the priority chain.
     *
     * In group conversations, passes the agent key so loadMessages()
     * can remap roles — this agent sees itself as assistant, everyone
     * else as participants with name prefixes.
     */
    public function conversationMessages(): array
    {
        $conversation = $this->resolveConversation();

        if ($conversation === null) {
            return [];
        }

        return $this->conversationService()
            ->loadMessages($conversation, $this->resolveMessageLimit(), $this->agentKey());
    }

    /**
     * Resolve the conversation service from the container.
     */
    protected function conversationService(): ConversationService
    {
        /** @phpstan-ignore-next-line */
        return app(ConversationService::class);
    }

    /**
     * Get the agent key if available on the host class.
     */
    protected function agentKey(): ?string
    {
        return method_exists($this, 'key') ? $this->key() : null;
    }
}
