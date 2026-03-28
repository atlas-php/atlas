<?php

declare(strict_types=1);

use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Messages\ToolResultMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Persistence\Enums\MessageRole;
use Atlasphp\Atlas\Persistence\Enums\MessageStatus;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\ConversationMessage;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\ExecutionStep;
use Atlasphp\Atlas\Persistence\Models\ExecutionToolCall;
use Atlasphp\Atlas\Persistence\Services\ConversationService;
use Illuminate\Database\Eloquent\Model;

it('creates a valid user message via factory', function () {
    $message = ConversationMessage::factory()->fromUser()->create();

    expect($message->exists)->toBeTrue()
        ->and($message->role)->toBe(MessageRole::User);
});

it('creates a valid assistant message via factory', function () {
    $message = ConversationMessage::factory()->fromAssistant()->create();

    expect($message->exists)->toBeTrue()
        ->and($message->role)->toBe(MessageRole::Assistant);
});

it('creates a valid system message via factory', function () {
    $message = ConversationMessage::factory()->system()->create();

    expect($message->exists)->toBeTrue()
        ->and($message->role)->toBe(MessageRole::System);
});

it('toAtlasMessage returns UserMessage for user role', function () {
    $message = ConversationMessage::factory()->fromUser()->create(['content' => 'Hello']);

    $atlas = $message->toAtlasMessage();

    expect($atlas)->toBeInstanceOf(UserMessage::class)
        ->and($atlas->content)->toBe('Hello');
});

it('toAtlasMessage returns AssistantMessage for assistant role', function () {
    $message = ConversationMessage::factory()->fromAssistant()->create(['content' => 'Hi there']);

    $atlas = $message->toAtlasMessage();

    expect($atlas)->toBeInstanceOf(AssistantMessage::class)
        ->and($atlas->content)->toBe('Hi there');
});

it('toAtlasMessage returns SystemMessage for system role', function () {
    $message = ConversationMessage::factory()->system()->create(['content' => 'You are helpful']);

    $atlas = $message->toAtlasMessage();

    expect($atlas)->toBeInstanceOf(SystemMessage::class)
        ->and($atlas->content)->toBe('You are helpful');
});

it('isFromUser returns true only for user messages', function () {
    $user = ConversationMessage::factory()->fromUser()->create();
    $assistant = ConversationMessage::factory()->fromAssistant()->create();

    expect($user->isFromUser())->toBeTrue()
        ->and($assistant->isFromUser())->toBeFalse();
});

it('isFromAssistant returns true only for assistant messages', function () {
    $assistant = ConversationMessage::factory()->fromAssistant()->create();
    $user = ConversationMessage::factory()->fromUser()->create();

    expect($assistant->isFromAssistant())->toBeTrue()
        ->and($user->isFromAssistant())->toBeFalse();
});

it('isSystem returns true only for system messages', function () {
    $system = ConversationMessage::factory()->system()->create();
    $user = ConversationMessage::factory()->fromUser()->create();

    expect($system->isSystem())->toBeTrue()
        ->and($user->isSystem())->toBeFalse();
});

it('markAsRead sets read_at and isRead/isUnread reflect state', function () {
    $message = ConversationMessage::factory()->create();

    expect($message->isUnread())->toBeTrue()
        ->and($message->isRead())->toBeFalse();

    $message->markAsRead();

    expect($message->isRead())->toBeTrue()
        ->and($message->isUnread())->toBeFalse()
        ->and($message->read_at)->not->toBeNull();
});

it('markDelivered transitions queued to delivered', function () {
    $message = ConversationMessage::factory()->queued()->create();

    expect($message->isQueued())->toBeTrue()
        ->and($message->isDelivered())->toBeFalse();

    $message->markDelivered();

    expect($message->isDelivered())->toBeTrue()
        ->and($message->isQueued())->toBeFalse();
});

it('isDelivered and isQueued reflect correct status', function () {
    $delivered = ConversationMessage::factory()->create(['status' => MessageStatus::Delivered]);
    $queued = ConversationMessage::factory()->queued()->create();

    expect($delivered->isDelivered())->toBeTrue()
        ->and($delivered->isQueued())->toBeFalse()
        ->and($queued->isQueued())->toBeTrue()
        ->and($queued->isDelivered())->toBeFalse();
});

it('canRetry returns true for last active assistant message', function () {
    $conversation = Conversation::factory()->create();

    $userMsg = ConversationMessage::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 1,
    ]);

    $assistantMsg = ConversationMessage::factory()->fromAssistant()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 2,
        'parent_id' => $userMsg->id,
    ]);

    expect($assistantMsg->canRetry())->toBeTrue();
});

it('canRetry returns false when conversation has continued', function () {
    $conversation = Conversation::factory()->create();

    $userMsg = ConversationMessage::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 1,
    ]);

    $assistantMsg = ConversationMessage::factory()->fromAssistant()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 2,
        'parent_id' => $userMsg->id,
    ]);

    // Another user message after the assistant message
    ConversationMessage::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 3,
        'is_active' => true,
    ]);

    expect($assistantMsg->canRetry())->toBeFalse();
});

it('parent and responses relationships work', function () {
    $conversation = Conversation::factory()->create();

    $parent = ConversationMessage::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 1,
    ]);

    $response1 = ConversationMessage::factory()->fromAssistant()->create([
        'conversation_id' => $conversation->id,
        'parent_id' => $parent->id,
        'sequence' => 2,
    ]);

    $response2 = ConversationMessage::factory()->fromAssistant()->create([
        'conversation_id' => $conversation->id,
        'parent_id' => $parent->id,
        'sequence' => 3,
    ]);

    expect($response1->parent->id)->toBe($parent->id)
        ->and($parent->responses)->toHaveCount(2)
        ->and($parent->responses->pluck('id')->toArray())
        ->toContain($response1->id, $response2->id);
});

it('addMessage creates correct record from UserMessage', function () {
    $conversation = Conversation::factory()->create();
    $service = app(ConversationService::class);

    $atlasMessage = new UserMessage(content: 'Hello world');
    $message = $service->addMessage($conversation, $atlasMessage);

    expect($message->exists)->toBeTrue()
        ->and($message->role)->toBe(MessageRole::User)
        ->and($message->content)->toBe('Hello world')
        ->and($message->status)->toBe(MessageStatus::Delivered);
});

it('addMessage creates correct record from AssistantMessage', function () {
    $conversation = Conversation::factory()->create();
    $service = app(ConversationService::class);

    $atlasMessage = new AssistantMessage(content: 'I can help');
    $message = $service->addMessage($conversation, $atlasMessage, agent: 'writer');

    expect($message->exists)->toBeTrue()
        ->and($message->role)->toBe(MessageRole::Assistant)
        ->and($message->content)->toBe('I can help')
        ->and($message->agent)->toBe('writer');
});

it('addMessage creates correct record from SystemMessage', function () {
    $conversation = Conversation::factory()->create();
    $service = app(ConversationService::class);

    $atlasMessage = new SystemMessage(content: 'You are a helpful assistant');
    $message = $service->addMessage($conversation, $atlasMessage);

    expect($message->exists)->toBeTrue()
        ->and($message->role)->toBe(MessageRole::System)
        ->and($message->content)->toBe('You are a helpful assistant');
});

it('addMessage throws on unknown message type', function () {
    $conversation = Conversation::factory()->create();
    $service = app(ConversationService::class);

    $toolResult = new ToolResultMessage(
        toolCallId: 'call_123',
        content: 'result',
        toolName: 'search',
    );

    $service->addMessage($conversation, $toolResult);
})->throws(InvalidArgumentException::class);

it('toAtlasMessagesWithTools reconstructs tool calls from execution step', function () {
    $execution = Execution::factory()->create();
    $step = ExecutionStep::factory()->create([
        'execution_id' => $execution->id,
    ]);

    ExecutionToolCall::factory()->create([
        'execution_id' => $execution->id,
        'step_id' => $step->id,
        'tool_call_id' => 'call_abc',
        'name' => 'search',
        'arguments' => ['query' => 'test'],
        'result' => 'Found 3 results',
    ]);

    $conversation = Conversation::factory()->create();
    $message = ConversationMessage::factory()->fromAssistant()->create([
        'conversation_id' => $conversation->id,
        'step_id' => $step->id,
        'content' => 'Let me search that for you',
    ]);

    $messages = $message->toAtlasMessagesWithTools();

    expect($messages)->toHaveCount(2)
        ->and($messages[0])->toBeInstanceOf(AssistantMessage::class)
        ->and($messages[0]->content)->toBe('Let me search that for you')
        ->and($messages[0]->toolCalls)->toHaveCount(1)
        ->and($messages[0]->toolCalls[0]->id)->toBe('call_abc')
        ->and($messages[0]->toolCalls[0]->name)->toBe('search')
        ->and($messages[0]->toolCalls[0]->arguments)->toBe(['query' => 'test'])
        ->and($messages[1])->toBeInstanceOf(ToolResultMessage::class)
        ->and($messages[1]->toolCallId)->toBe('call_abc')
        ->and($messages[1]->content)->toBe('Found 3 results');
});

it('toAtlasMessagesWithTools returns simple AssistantMessage when no step', function () {
    $message = ConversationMessage::factory()->fromAssistant()->create([
        'content' => 'Hello there',
        'step_id' => null,
    ]);

    $messages = $message->toAtlasMessagesWithTools();

    expect($messages)->toHaveCount(1)
        ->and($messages[0])->toBeInstanceOf(AssistantMessage::class)
        ->and($messages[0]->content)->toBe('Hello there')
        ->and($messages[0]->toolCalls)->toBeEmpty();
});

it('siblingGroups returns grouped retry runs', function () {
    $conversation = Conversation::factory()->create();

    $parent = ConversationMessage::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 1,
    ]);

    // First retry group
    ConversationMessage::factory()->fromAssistant()->create([
        'conversation_id' => $conversation->id,
        'parent_id' => $parent->id,
        'sequence' => 2,
    ]);

    // Second retry group
    ConversationMessage::factory()->fromAssistant()->create([
        'conversation_id' => $conversation->id,
        'parent_id' => $parent->id,
        'sequence' => 3,
    ]);

    $groups = ConversationMessage::where('parent_id', $parent->id)->first()->siblingGroups();

    expect($groups)->toHaveCount(2);
});

it('siblingCount returns correct count', function () {
    $conversation = Conversation::factory()->create();

    $parent = ConversationMessage::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 1,
    ]);

    ConversationMessage::factory()->fromAssistant()->create([
        'conversation_id' => $conversation->id,
        'parent_id' => $parent->id,
        'sequence' => 2,
    ]);

    ConversationMessage::factory()->fromAssistant()->create([
        'conversation_id' => $conversation->id,
        'parent_id' => $parent->id,
        'sequence' => 3,
    ]);

    $child = ConversationMessage::where('parent_id', $parent->id)->first();

    expect($child->siblingCount())->toBe(2);
});

it('siblingIndex returns 1-based index of current message', function () {
    $conversation = Conversation::factory()->create();

    $parent = ConversationMessage::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 1,
    ]);

    $first = ConversationMessage::factory()->fromAssistant()->create([
        'conversation_id' => $conversation->id,
        'parent_id' => $parent->id,
        'sequence' => 2,
    ]);

    $second = ConversationMessage::factory()->fromAssistant()->create([
        'conversation_id' => $conversation->id,
        'parent_id' => $parent->id,
        'sequence' => 3,
    ]);

    expect($first->siblingIndex())->toBe(1)
        ->and($second->siblingIndex())->toBe(2);
});

it('ownerName returns agent key for agent-authored message', function () {
    $message = ConversationMessage::factory()->fromAssistant('writer-agent')->create();

    expect($message->ownerName())->toBe('writer-agent');
});

it('ownerName returns null for messages without author or agent', function () {
    $message = ConversationMessage::factory()->fromUser()->create([
        'agent' => null,
        'owner_type' => null,
        'owner_id' => null,
    ]);

    expect($message->ownerName())->toBeNull();
});

it('scopeActive filters to active messages only', function () {
    $conversation = Conversation::factory()->create();

    ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'is_active' => true,
        'sequence' => 1,
    ]);
    ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'is_active' => true,
        'sequence' => 2,
    ]);
    ConversationMessage::factory()->inactive()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 3,
    ]);

    expect(ConversationMessage::where('conversation_id', $conversation->id)->active()->count())->toBe(2);
});

it('scopeDelivered filters delivered messages', function () {
    $conversation = Conversation::factory()->create();

    ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'status' => MessageStatus::Delivered,
        'sequence' => 1,
    ]);
    ConversationMessage::factory()->queued()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 2,
    ]);

    expect(ConversationMessage::where('conversation_id', $conversation->id)->delivered()->count())->toBe(1);
});

it('scopeQueued filters queued messages', function () {
    $conversation = Conversation::factory()->create();

    ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'status' => MessageStatus::Delivered,
        'sequence' => 1,
    ]);
    ConversationMessage::factory()->queued()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 2,
    ]);
    ConversationMessage::factory()->queued()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 3,
    ]);

    expect(ConversationMessage::where('conversation_id', $conversation->id)->queued()->count())->toBe(2);
});

it('scopeByOwner filters by polymorphic owner', function () {
    $conversation = Conversation::factory()->create();
    ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'owner_type' => 'App\\Models\\User',
        'owner_id' => 5,
        'sequence' => 1,
    ]);
    ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'owner_type' => 'App\\Models\\User',
        'owner_id' => 10,
        'sequence' => 2,
    ]);

    $author = new class extends Model
    {
        protected $table = 'users';

        public function getMorphClass(): string
        {
            return 'App\\Models\\User';
        }

        public function getKey(): mixed
        {
            return 5;
        }
    };

    $results = ConversationMessage::byOwner($author)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->owner_id)->toBe(5);
});

it('scopeByAgent filters by agent key', function () {
    $conversation = Conversation::factory()->create();
    ConversationMessage::factory()->fromAssistant('agent-a')->create([
        'conversation_id' => $conversation->id,
        'sequence' => 1,
    ]);
    ConversationMessage::factory()->fromAssistant('agent-b')->create([
        'conversation_id' => $conversation->id,
        'sequence' => 2,
    ]);

    $results = ConversationMessage::byAgent('agent-a')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->agent)->toBe('agent-a');
});

it('siblings relationship returns messages with same parent', function () {
    $conversation = Conversation::factory()->create();
    $parent = ConversationMessage::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 1,
    ]);
    $sibling1 = ConversationMessage::factory()->fromAssistant('agent')->create([
        'conversation_id' => $conversation->id,
        'parent_id' => $parent->id,
        'sequence' => 2,
    ]);
    ConversationMessage::factory()->fromAssistant('agent')->create([
        'conversation_id' => $conversation->id,
        'parent_id' => $parent->id,
        'sequence' => 3,
    ]);

    // siblings excludes self — only peer messages with the same parent
    expect($sibling1->siblings)->toHaveCount(1);
});
