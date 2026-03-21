<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Middleware;

use Atlasphp\Atlas\Agents\Agent;
use Atlasphp\Atlas\Executor\ExecutorResult;
use Atlasphp\Atlas\Messages\Message;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Middleware\AgentContext;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;
use Atlasphp\Atlas\Persistence\Jobs\ProcessQueuedMessage;
use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\ExecutionStep;
use Atlasphp\Atlas\Persistence\Models\MessageAttachment;
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
     * @param  Closure(AgentContext): ExecutorResult  $next
     */
    public function handle(AgentContext $context, Closure $next): ExecutorResult
    {
        $agent = $context->agent;

        // Only activate for agents using HasConversations
        if ($agent === null || ! in_array(HasConversations::class, class_uses_recursive($agent), true)) {
            return $next($context);
        }

        /** @var Agent&HasConversations $agent */

        // Validate conversation exists for respond/retry modes
        if (($agent->isRespondMode() || $agent->isRetrying()) && $agent->resolveConversation() === null) {
            throw new \RuntimeException(
                'respond() and retry() require forConversation($id).'
            );
        }

        $conversation = $agent->resolveConversation();

        if ($conversation === null) {
            return $next($context);
        }

        // ── Retry preparation — deactivate current response BEFORE loading history
        if ($agent->isRetrying()) {
            $agent->setRetryParentId($this->conversations->prepareRetry($conversation));
        }

        // ── Capture the user message BEFORE prepending history
        $userMessage = $this->findUserMessage($context->messages);

        // ── Load conversation history into context
        // Role remapping happens inside for group conversations.
        // In retry mode, the old response is now inactive — only the active thread loads.
        $history = $agent->conversationMessages();

        if (! empty($history)) {
            $context->messages = array_merge($history, $context->messages);
        }

        // Inject conversation_id into meta so delegation tools can access it
        $context->meta['conversation_id'] = $conversation->id;

        // ── Execute the agent ────────────────────────────────────
        $result = $next($context);

        // ── Store messages after execution (atomic) ──────────────
        // Wrapped in a transaction to prevent sequence collisions
        // under concurrent load and ensure all-or-nothing writes.

        $agentKey = $agent->key();

        /** @var array<int, \Atlasphp\Atlas\Persistence\Models\Message> $storedMessages */
        $storedMessages = DB::transaction(function () use ($agent, $agentKey, $context, $conversation, $userMessage, $result): array {
            $author = $agent->resolveAuthor();
            $parentId = null;

            if ($agent->isRetrying()) {
                $parentId = $agent->getRetryParentId();

            } elseif (! $agent->isRespondMode() && $userMessage !== null) {
                // Store the user message, use its ID as parent
                $stored = $this->conversations->addMessage(
                    $conversation,
                    $userMessage,
                    author: $author,
                );
                $stored->markAsRead(); // Agent already processed it
                $parentId = $stored->id;

            } else {
                // Respond mode — find the last active user message as parent
                $parentId = $this->conversations->lastUserMessageId($conversation);
            }

            // ── Store one assistant message per step ─────────────
            // Each step's visible text becomes an assistant message row.
            // Tool call data lives in execution tables — NOT duplicated here.
            // The step_id FK links each message to its execution step so
            // loadMessages() can reconstruct tool calls at read time.

            $executionId = $context->meta['execution_id'] ?? null;
            $stepData = $this->buildStepData($result, $executionId);

            return $this->conversations->addAssistantMessages(
                $conversation,
                $stepData,
                agent: $agentKey,
                parentId: $parentId,
            );
        });

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
     * Attach assets created during tool execution to their corresponding assistant messages.
     *
     * Assets produced by tools are linked via execution_id and metadata.tool_call_id
     * (not a direct FK on tool_calls — tools can produce multiple assets).
     * Each assistant message has a step_id. Tool calls belong to steps.
     * We match assets to messages by tracing: asset → tool_call (via metadata) → step → message.
     *
     * @param  array<int, \Atlasphp\Atlas\Persistence\Models\Message>  $storedMessages
     */
    protected function attachToolAssets(Execution $execution, array $storedMessages): void
    {
        /** @var class-string<Asset> $assetModel */
        $assetModel = config('atlas.persistence.models.asset', Asset::class);

        // Find all assets created during this execution by tool calls
        $toolAssets = $assetModel::where('execution_id', $execution->id)
            ->whereJsonContains('metadata->source', 'tool_execution')
            ->get();

        if ($toolAssets->isEmpty()) {
            return;
        }

        // Build a map of step_id → tool call IDs for matching
        $stepToolCallIds = [];

        foreach ($execution->toolCalls()->get() as $toolCall) {
            $stepToolCallIds[$toolCall->step_id][] = $toolCall->id;
        }

        $attachmentModel = config(
            'atlas.persistence.models.message_attachment',
            MessageAttachment::class,
        );

        foreach ($storedMessages as $message) {
            if ($message->step_id === null) {
                continue;
            }

            // Get the tool call IDs that belong to this message's step
            $toolCallIdsForStep = $stepToolCallIds[$message->step_id] ?? [];

            if ($toolCallIdsForStep === []) {
                continue;
            }

            // Find assets whose metadata.tool_call_id matches any tool call in this step
            $stepAssets = $toolAssets->filter(
                fn (Asset $asset) => in_array(
                    $asset->metadata['tool_call_id'] ?? null,
                    $toolCallIdsForStep,
                    true,
                )
            );

            foreach ($stepAssets as $asset) {
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

    /**
     * Build step data for storing assistant messages.
     *
     * Each step becomes one assistant message. If execution tracking is active,
     * we look up the DB step IDs so messages link to their execution steps.
     * This enables loadMessages() to reconstruct tool calls from execution data.
     *
     * @return array<array{text: string, step_id: int|null}>
     */
    protected function buildStepData(ExecutorResult $result, ?int $executionId): array
    {
        // If execution tracking is active, look up step IDs by sequence
        $stepIds = [];

        if ($executionId !== null) {
            $stepModel = config('atlas.persistence.models.execution_step', ExecutionStep::class);
            $stepIds = $stepModel::where('execution_id', $executionId)
                ->orderBy('sequence')
                ->pluck('id')
                ->all();
        }

        $data = [];

        foreach ($result->steps as $index => $step) {
            $data[] = [
                'text' => $step->text,
                'step_id' => $stepIds[$index] ?? null,
            ];
        }

        return $data;
    }
}
