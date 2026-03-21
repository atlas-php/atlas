<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence;

use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Persistence\Enums\MessageStatus;
use Atlasphp\Atlas\Persistence\Models\Message;
use Atlasphp\Atlas\Persistence\Services\ConversationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Schedules processing of the next queued conversation message.
 *
 * This job is a pure gatekeeper — it checks if the conversation is ready,
 * delivers the next message, then delegates execution to the standard
 * Atlas::agent() flow with the full request context (variables, meta,
 * provider options) that was stored when the message was queued.
 *
 * The agent's own middleware stack handles all lifecycle concerns. The
 * execution path is identical to a direct consumer call — only timing differs.
 *
 * Unique per conversation to prevent concurrent processing.
 */
class ProcessQueuedMessage implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public int $backoff;

    public int $timeout;

    /** @var int|null Tracks the delivered message ID for failure recovery. */
    protected ?int $deliveredMessageId = null;

    public function __construct(
        public readonly int $conversationId,
        public readonly string $agentKey,
    ) {
        $this->tries = (int) config('atlas.queue.tries', 3);
        $this->backoff = (int) config('atlas.queue.backoff', 30);
        $this->timeout = (int) config('atlas.queue.timeout', 300);
    }

    public function uniqueId(): string
    {
        return 'atlas-queued-'.$this->conversationId;
    }

    public function handle(ConversationService $conversations): void
    {
        $conversation = $conversations->find($this->conversationId);

        // Not ready — re-release so we retry after the active execution finishes.
        if ($conversations->hasActiveExecution($conversation)) {
            $this->release(5);

            return;
        }

        $message = $conversations->deliverNextQueued($conversation);

        if ($message === null) {
            return;
        }

        $this->deliveredMessageId = $message->id;

        // Rebuild and execute the agent request using the same path a consumer
        // would take. The request context (variables, meta, provider options)
        // was stored in metadata when the message was queued, so the execution
        // is identical to the original direct call.
        try {
            $this->executeAgent($message);
        } catch (Throwable $e) {
            $message->update(['status' => MessageStatus::Queued]);

            throw $e;
        }
    }

    /**
     * Execute the agent with the full request context from the queued message.
     *
     * Rebuilds the exact same agent call the consumer would have made,
     * applying any stored variables, meta, and provider options.
     */
    protected function executeAgent(Message $message): void
    {
        $context = $message->metadata ?? [];

        $request = Atlas::agent($this->agentKey)
            ->forConversation($this->conversationId)
            ->message($message->content ?? '');

        if (isset($context['variables']) && $context['variables'] !== []) {
            $request->withVariables($context['variables']);
        }

        if (isset($context['meta']) && $context['meta'] !== []) {
            $request->withMeta($context['meta']);
        }

        if (isset($context['provider_options']) && $context['provider_options'] !== []) {
            $request->withProviderOptions($context['provider_options']);
        }

        $request->asText();
    }

    /**
     * Called by Laravel when all retries are exhausted.
     */
    public function failed(Throwable $exception): void
    {
        if ($this->deliveredMessageId === null) {
            return;
        }

        /** @var class-string<Message> $messageModel */
        $messageModel = config('atlas.persistence.models.message', Message::class);
        $message = $messageModel::find($this->deliveredMessageId);

        $message?->update(['status' => MessageStatus::Failed]);
    }
}
