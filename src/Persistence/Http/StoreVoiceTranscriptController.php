<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Http;

use Atlasphp\Atlas\Enums\Role;
use Atlasphp\Atlas\Events\ConversationMessageStored;
use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Services\ConversationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Package-provided endpoint for storing voice transcript turns.
 *
 * Persists voice turns as regular conversation messages tagged with
 * voice metadata, so consumers get transcript persistence for free
 * when Atlas persistence is enabled.
 */
class StoreVoiceTranscriptController
{
    public function __construct(
        protected readonly ConversationService $conversations,
    ) {}

    public function __invoke(Request $request, string $sessionId): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => 'required|integer',
            'turns' => 'required|array|min:1',
            'turns.*.role' => 'required|string|in:user,assistant',
            'turns.*.transcript' => 'required|string|min:1',
            'agent' => 'sometimes|nullable|string',
            'author_type' => 'sometimes|nullable|string',
            'author_id' => 'sometimes|nullable',
        ]);

        $conversation = $this->conversations->find($validated['conversation_id']);
        $author = $this->resolveAuthor($validated['author_type'] ?? null, $validated['author_id'] ?? null);
        $agent = $validated['agent'] ?? null;

        $storedIds = [];
        $lastUserMessageId = null;

        foreach ($validated['turns'] as $turn) {
            $message = $turn['role'] === 'user'
                ? new UserMessage(content: $turn['transcript'])
                : new AssistantMessage(content: $turn['transcript']);

            $parentId = $turn['role'] === 'assistant' ? $lastUserMessageId : null;
            $messageAuthor = $turn['role'] === 'user' ? $author : null;
            $messageAgent = $turn['role'] === 'assistant' ? $agent : null;

            $stored = $this->conversations->addMessage(
                conversation: $conversation,
                message: $message,
                author: $messageAuthor,
                agent: $messageAgent,
                parentId: $parentId,
                metadata: [
                    'source' => 'voice',
                    'session_id' => $sessionId,
                ],
            );

            // Voice messages are always considered read
            $stored->markAsRead();

            if ($turn['role'] === 'user') {
                $lastUserMessageId = $stored->id;
            }

            $storedIds[] = $stored->id;

            event(new ConversationMessageStored(
                conversationId: $conversation->id,
                messageId: $stored->id,
                role: $turn['role'] === 'user' ? Role::User : Role::Assistant,
                agent: $messageAgent,
            ));
        }

        // Complete the voice execution if one exists for this session
        Execution::completeVoiceSession($sessionId);

        return response()->json(['stored' => $storedIds]);
    }

    /**
     * Resolve the author model from a morph alias or class name and ID.
     *
     * Accepts registered morph map aliases first, then falls back to class
     * names that are valid Eloquent model subclasses.
     */
    private function resolveAuthor(?string $authorType, ?int $authorId): ?Model
    {
        if ($authorType === null || $authorId === null) {
            return null;
        }

        // Try morph map alias first
        $modelClass = Relation::getMorphedModel($authorType);

        // Fall back to class name if it's a valid Model subclass
        if ($modelClass === null && class_exists($authorType) && is_subclass_of($authorType, Model::class)) {
            $modelClass = $authorType;
        }

        if ($modelClass === null) {
            return null;
        }

        return $modelClass::find($authorId);
    }
}
