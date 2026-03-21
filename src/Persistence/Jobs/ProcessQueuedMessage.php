<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Jobs;

use Atlasphp\Atlas\Facades\Atlas;
use Atlasphp\Atlas\Persistence\Enums\MessageStatus;
use Atlasphp\Atlas\Persistence\Services\ConversationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Class ProcessQueuedMessage
 *
 * Processes the next queued message in a conversation after the current execution
 * completes. Checks for active executions to prevent concurrent processing, delivers
 * the next queued message, and triggers a new agent execution.
 */
class ProcessQueuedMessage implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $conversationId,
        public readonly string $agentKey,
    ) {}

    public function uniqueId(): string
    {
        return 'atlas-queued-'.$this->conversationId;
    }

    public function handle(ConversationService $conversations): void
    {
        $conversation = $conversations->find($this->conversationId);

        // Don't process if there's already an active execution —
        // re-release so we retry after the active execution finishes.
        if ($conversations->hasActiveExecution($conversation)) {
            $this->release(5);

            return;
        }

        // Deliver the next queued message
        $message = $conversations->deliverNextQueued($conversation);

        if ($message === null) {
            return; // Queue is empty
        }

        // Trigger agent execution with the delivered message.
        // Wrapped in try/catch so the delivered message isn't silently lost
        // if the execution fails (e.g., agent stub, provider error).
        try {
            Atlas::agent($this->agentKey)
                ->forConversation($this->conversationId)
                ->message($message->content ?? '')
                ->asText();
        } catch (\Throwable $e) {
            // Re-queue the message so it can be retried
            $message->update(['status' => MessageStatus::Queued]);

            throw $e;
        }

        // PersistConversation middleware will handle:
        //   - Storing the response
        //   - Checking for more queued messages
        //   - Dispatching another ProcessQueuedMessage if needed
    }
}
