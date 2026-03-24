<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Middleware;

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Enums\Role;
use Atlasphp\Atlas\Events\ConversationMessageStored;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Executor\ExecutorResult;
use Atlasphp\Atlas\Input\Input;
use Atlasphp\Atlas\Messages\Message;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Middleware\AgentContext;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;
use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\MessageAttachment;
use Atlasphp\Atlas\Persistence\ProcessQueuedMessage;
use Atlasphp\Atlas\Persistence\Services\ConversationService;
use Atlasphp\Atlas\Persistence\Services\ExecutionService;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Class PersistConversation
 *
 * Agent-layer middleware that wraps the entire execution to persist conversation
 * state. Loads conversation history before execution and stores user/assistant
 * messages after. Handles respond mode, retry mode, and queued message dispatch.
 */
class PersistConversation
{
    public function __construct(
        protected readonly ConversationService $conversations,
        protected readonly ExecutionService $executionService,
    ) {}

    /**
     * @param  Closure(AgentContext): mixed  $next
     */
    public function handle(AgentContext $context, Closure $next): mixed
    {
        $agent = $context->agent;

        // Only activate for agents using HasConversations
        if ($agent === null || ! in_array(HasConversations::class, class_uses_recursive($agent), true)) {
            return $next($context);
        }

        /** @var Agent&HasConversations $agent */

        // Validate conversation exists for respond/retry modes
        if (($agent->isRespondMode() || $agent->isRetrying()) && $agent->resolveConversation() === null) {
            throw new AtlasException(
                'respond() and retry() require forConversation($id).'
            );
        }

        $conversation = $agent->resolveConversation();

        if ($conversation === null) {
            return $next($context);
        }

        // Capture consumer-provided metadata BEFORE other middleware
        // (WireMemory etc.) injects internal keys into context->meta.
        // The request->meta is the original from withMeta().
        $consumerMeta = $context->request->meta;

        // ── Retry preparation — deactivate current response BEFORE loading history
        if ($agent->isRetrying()) {
            $agent->setRetryParentId($this->conversations->prepareRetry($conversation));
        }

        // ── Capture the user message BEFORE prepending history
        // Check context->messages first (from withMessages), then the request
        // message (from ->message('text')). The latter is the common case for
        // agents called via Atlas::agent()->message()->asText().
        $userMessage = $this->findUserMessage($context->messages);

        if ($userMessage === null && $context->request->message !== null) {
            $userMessage = new UserMessage(
                content: $context->request->message,
                media: $context->request->messageMedia,
            );
        }

        // ── Load conversation history into context and request
        // Role remapping happens inside for group conversations.
        // In retry mode, the old response is now inactive — only the active thread loads.
        $history = $agent->conversationMessages();

        // Guard: respond() and retry() require at least one message in the conversation.
        // Without history, the provider receives no input and returns a 400 error.
        if (empty($history) && ($agent->isRespondMode() || $agent->isRetrying()) && $userMessage === null) {
            throw new AtlasException(
                'respond() and retry() require at least one message in the conversation. '
                .'Use message() to send the first message.'
            );
        }

        if (! empty($history)) {
            $context->messages = array_merge($history, $context->messages);

            // Replace request messages with history + existing so the
            // driver sends conversation context to the provider.
            $merged = array_merge($history, $context->request->messages);
            $context->request = $context->request->withReplacedMessages($merged);
        }

        // Store consumer metadata on the conversation if it has none yet
        if ($conversation->metadata === null && $consumerMeta !== []) {
            $conversation->update(['metadata' => $consumerMeta]);
        }

        // Inject conversation_id into meta so delegation tools can access it
        $context->meta['conversation_id'] = $conversation->id;

        $agentKey = $agent->key();
        $parentId = null;

        // ── Resolve parent message for assistant response ─────────
        // User messages are stored eagerly by AgentRequest before dispatch.
        // This middleware only needs to find the parent for linking the assistant response.
        if ($agent->isRetrying()) {
            $parentId = $agent->getRetryParentId();
        } else {
            $parentId = $this->conversations->lastUserMessageId($conversation);
        }

        // ── Execute the agent ────────────────────────────────────
        $result = $next($context);

        // Conversation persistence only applies to ExecutorResult (agent with tools).
        // Tool-free agent calls return TextResponse and skip persistence here.
        if (! $result instanceof ExecutorResult) {
            return $result;
        }

        // ── Store assistant response after execution (atomic) ────

        /** @var array<int, \Atlasphp\Atlas\Persistence\Models\Message> $storedMessages */
        $storedMessages = DB::transaction(function () use ($agentKey, $conversation, $result, $parentId): array {
            // Safe to access here: completeStep() retains the reference, and
            // this middleware wraps TrackExecution so the scoped service is still active.
            $lastStepId = $this->executionService->currentStep()?->id;

            $storedMessages = $this->conversations->addAssistantMessages(
                $conversation,
                [['text' => $result->text, 'step_id' => $lastStepId]],
                agent: $agentKey,
                parentId: $parentId,
            );

            // Link the assistant message to its execution
            $execution = $this->executionService->getExecution();

            if ($execution !== null && $storedMessages !== []) {
                $execution->update(['message_id' => $storedMessages[0]->id]);
            }

            return $storedMessages;
        });

        // ── Dispatch persistence events after transaction commits ─
        foreach ($storedMessages as $storedMessage) {
            event(new ConversationMessageStored(
                conversationId: $conversation->id,
                messageId: $storedMessage->id,
                role: Role::Assistant,
                agent: $agentKey,
            ));
        }

        // Attach conversation ID to the response
        $result->conversationId = $conversation->id;

        // ── Auto-attach tool-created assets to messages ───────
        $execution = $this->executionService->getExecution();

        if ($execution !== null) {
            try {
                $this->attachToolAssets($execution, $storedMessages);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // ── Check for queued messages ────────────────────────────
        // If there are queued user messages waiting, dispatch a job
        // to process the next one now that this execution is done.
        $nextQueued = $this->conversations->nextQueuedMessage($conversation);

        if ($nextQueued !== null) {
            $job = new ProcessQueuedMessage(
                conversationId: $conversation->id,
                agentKey: $agentKey,
            );

            $connection = config('atlas.queue.connection');
            $queue = config('atlas.queue.queue', 'default');

            if ($connection !== null) {
                $job->onConnection($connection);
            }

            $job->onQueue($queue);

            if (config('atlas.queue.after_commit', true)) {
                $job->afterCommit();
            }

            dispatch($job);
        }

        return $result;
    }

    /**
     * Attach assets created during tool execution to the assistant message.
     *
     * All tool-created assets from this execution are linked to the single
     * stored assistant message. Asset metadata carries tool_call_id and
     * tool_name for tracing which tool produced the asset.
     *
     * @param  array<int, \Atlasphp\Atlas\Persistence\Models\Message>  $storedMessages
     */
    protected function attachToolAssets(Execution $execution, array $storedMessages): void
    {
        if ($storedMessages === []) {
            return;
        }

        /** @var class-string<Asset> $assetModel */
        $assetModel = config('atlas.persistence.models.asset', Asset::class);

        $toolAssets = $assetModel::where('execution_id', $execution->id)
            ->whereJsonContains('metadata->source', 'tool_execution')
            ->get();

        if ($toolAssets->isEmpty()) {
            return;
        }

        $attachmentModel = config(
            'atlas.persistence.models.message_attachment',
            MessageAttachment::class,
        );

        $message = $storedMessages[0];

        foreach ($toolAssets as $asset) {
            $attachmentModel::create([
                'message_id' => $message->id,
                'asset_id' => $asset->id,
                'metadata' => [
                    'tool_call_id' => $asset->metadata['tool_call_id'] ?? null,
                    'tool_name' => $asset->metadata['tool_name'] ?? null,
                ],
            ]);
        }
    }

    /**
     * Find the user message in the current messages array.
     *
     * @param  array<int, Message>  $messages
     */
    protected function findUserMessage(array $messages): ?UserMessage
    {
        foreach (array_reverse($messages) as $message) {
            if ($message instanceof UserMessage) {
                return $message;
            }
        }

        return null;
    }
}
