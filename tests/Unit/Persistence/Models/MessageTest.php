<?php

declare(strict_types=1);

use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Messages\ToolResultMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Persistence\Enums\MessageRole;
use Atlasphp\Atlas\Persistence\Enums\MessageStatus;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\ExecutionStep;
use Atlasphp\Atlas\Persistence\Models\ExecutionToolCall;
use Atlasphp\Atlas\Persistence\Models\Message;
use Illuminate\Database\Eloquent\Model;

it('creates a valid user message via factory', function () {
    $message = Message::factory()->fromUser()->create();

    expect($message->exists)->toBeTrue()
        ->and($message->role)->toBe(MessageRole::User);
});

it('creates a valid assistant message via factory', function () {
    $message = Message::factory()->fromAssistant()->create();

    expect($message->exists)->toBeTrue()
        ->and($message->role)->toBe(MessageRole::Assistant);
});

it('creates a valid system message via factory', function () {
    $message = Message::factory()->system()->create();

    expect($message->exists)->toBeTrue()
        ->and($message->role)->toBe(MessageRole::System);
});

it('toAtlasMessage returns UserMessage for user role', function () {
    $message = Message::factory()->fromUser()->create(['content' => 'Hello']);

    $atlas = $message->toAtlasMessage();

    expect($atlas)->toBeInstanceOf(UserMessage::class)
        ->and($atlas->content)->toBe('Hello');
});

it('toAtlasMessage returns AssistantMessage for assistant role', function () {
    $message = Message::factory()->fromAssistant()->create(['content' => 'Hi there']);

    $atlas = $message->toAtlasMessage();

    expect($atlas)->toBeInstanceOf(AssistantMessage::class)
        ->and($atlas->content)->toBe('Hi there');
});

it('toAtlasMessage returns SystemMessage for system role', function () {
    $message = Message::factory()->system()->create(['content' => 'You are helpful']);

    $atlas = $message->toAtlasMessage();

    expect($atlas)->toBeInstanceOf(SystemMessage::class)
        ->and($atlas->content)->toBe('You are helpful');
});

it('isFromUser returns true only for user messages', function () {
    $user = Message::factory()->fromUser()->create();
    $assistant = Message::factory()->fromAssistant()->create();

    expect($user->isFromUser())->toBeTrue()
        ->and($assistant->isFromUser())->toBeFalse();
});

it('isFromAssistant returns true only for assistant messages', function () {
    $assistant = Message::factory()->fromAssistant()->create();
    $user = Message::factory()->fromUser()->create();

    expect($assistant->isFromAssistant())->toBeTrue()
        ->and($user->isFromAssistant())->toBeFalse();
});

it('isSystem returns true only for system messages', function () {
    $system = Message::factory()->system()->create();
    $user = Message::factory()->fromUser()->create();

    expect($system->isSystem())->toBeTrue()
        ->and($user->isSystem())->toBeFalse();
});

it('markAsRead sets read_at and isRead/isUnread reflect state', function () {
    $message = Message::factory()->create();

    expect($message->isUnread())->toBeTrue()
        ->and($message->isRead())->toBeFalse();

    $message->markAsRead();

    expect($message->isRead())->toBeTrue()
        ->and($message->isUnread())->toBeFalse()
        ->and($message->read_at)->not->toBeNull();
});

it('markDelivered transitions queued to delivered', function () {
    $message = Message::factory()->queued()->create();

    expect($message->isQueued())->toBeTrue()
        ->and($message->isDelivered())->toBeFalse();

    $message->markDelivered();

    expect($message->isDelivered())->toBeTrue()
        ->and($message->isQueued())->toBeFalse();
});

it('isDelivered and isQueued reflect correct status', function () {
    $delivered = Message::factory()->create(['status' => MessageStatus::Delivered]);
    $queued = Message::factory()->queued()->create();

    expect($delivered->isDelivered())->toBeTrue()
        ->and($delivered->isQueued())->toBeFalse()
        ->and($queued->isQueued())->toBeTrue()
        ->and($queued->isDelivered())->toBeFalse();
});

it('canRetry returns true for last active assistant message', function () {
    $conversation = Conversation::factory()->create();

    $userMsg = Message::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 0,
    ]);

    $assistantMsg = Message::factory()->fromAssistant()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 1,
        'parent_id' => $userMsg->id,
    ]);

    expect($assistantMsg->canRetry())->toBeTrue();
});

it('canRetry returns false when conversation has continued', function () {
    $conversation = Conversation::factory()->create();

    $userMsg = Message::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 0,
    ]);

    $assistantMsg = Message::factory()->fromAssistant()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 1,
        'parent_id' => $userMsg->id,
    ]);

    // Another user message after the assistant message
    Message::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 2,
        'is_active' => true,
    ]);

    expect($assistantMsg->canRetry())->toBeFalse();
});

it('parent and responses relationships work', function () {
    $conversation = Conversation::factory()->create();

    $parent = Message::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 0,
    ]);

    $response1 = Message::factory()->fromAssistant()->create([
        'conversation_id' => $conversation->id,
        'parent_id' => $parent->id,
        'sequence' => 1,
    ]);

    $response2 = Message::factory()->fromAssistant()->create([
        'conversation_id' => $conversation->id,
        'parent_id' => $parent->id,
        'sequence' => 2,
    ]);

    expect($response1->parent->id)->toBe($parent->id)
        ->and($parent->responses)->toHaveCount(2)
        ->and($parent->responses->pluck('id')->toArray())
        ->toContain($response1->id, $response2->id);
});

it('fromAtlasMessage creates correct record from UserMessage', function () {
    $conversation = Conversation::factory()->create();

    $atlasMessage = new UserMessage(content: 'Hello world');
    $message = Message::fromAtlasMessage($atlasMessage, $conversation->id, 0);

    expect($message->exists)->toBeTrue()
        ->and($message->role)->toBe(MessageRole::User)
        ->and($message->content)->toBe('Hello world')
        ->and($message->sequence)->toBe(0)
        ->and($message->status)->toBe(MessageStatus::Delivered);
});

it('fromAtlasMessage creates correct record from AssistantMessage', function () {
    $conversation = Conversation::factory()->create();

    $atlasMessage = new AssistantMessage(content: 'I can help');
    $message = Message::fromAtlasMessage($atlasMessage, $conversation->id, 1, agent: 'writer');

    expect($message->exists)->toBeTrue()
        ->and($message->role)->toBe(MessageRole::Assistant)
        ->and($message->content)->toBe('I can help')
        ->and($message->agent)->toBe('writer');
});

it('fromAtlasMessage creates correct record from SystemMessage', function () {
    $conversation = Conversation::factory()->create();

    $atlasMessage = new SystemMessage(content: 'You are a helpful assistant');
    $message = Message::fromAtlasMessage($atlasMessage, $conversation->id, 0);

    expect($message->exists)->toBeTrue()
        ->and($message->role)->toBe(MessageRole::System)
        ->and($message->content)->toBe('You are a helpful assistant');
});

it('fromAtlasMessage throws on unknown message type', function () {
    $conversation = Conversation::factory()->create();

    $toolResult = new ToolResultMessage(
        toolCallId: 'call_123',
        content: 'result',
        toolName: 'search',
    );

    Message::fromAtlasMessage($toolResult, $conversation->id, 0);
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
    $message = Message::factory()->fromAssistant()->create([
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
    $message = Message::factory()->fromAssistant()->create([
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

    $parent = Message::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 0,
    ]);

    // First retry group
    Message::factory()->fromAssistant()->create([
        'conversation_id' => $conversation->id,
        'parent_id' => $parent->id,
        'sequence' => 1,
    ]);

    // Second retry group
    Message::factory()->fromAssistant()->create([
        'conversation_id' => $conversation->id,
        'parent_id' => $parent->id,
        'sequence' => 2,
    ]);

    $groups = Message::where('parent_id', $parent->id)->first()->siblingGroups();

    expect($groups)->toHaveCount(2);
});

it('siblingCount returns correct count', function () {
    $conversation = Conversation::factory()->create();

    $parent = Message::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 0,
    ]);

    Message::factory()->fromAssistant()->create([
        'conversation_id' => $conversation->id,
        'parent_id' => $parent->id,
        'sequence' => 1,
    ]);

    Message::factory()->fromAssistant()->create([
        'conversation_id' => $conversation->id,
        'parent_id' => $parent->id,
        'sequence' => 2,
    ]);

    $child = Message::where('parent_id', $parent->id)->first();

    expect($child->siblingCount())->toBe(2);
});

it('siblingIndex returns 1-based index of current message', function () {
    $conversation = Conversation::factory()->create();

    $parent = Message::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 0,
    ]);

    $first = Message::factory()->fromAssistant()->create([
        'conversation_id' => $conversation->id,
        'parent_id' => $parent->id,
        'sequence' => 1,
    ]);

    $second = Message::factory()->fromAssistant()->create([
        'conversation_id' => $conversation->id,
        'parent_id' => $parent->id,
        'sequence' => 2,
    ]);

    expect($first->siblingIndex())->toBe(1)
        ->and($second->siblingIndex())->toBe(2);
});

it('authorName returns agent key for agent-authored message', function () {
    $message = Message::factory()->fromAssistant('writer-agent')->create();

    expect($message->authorName())->toBe('writer-agent');
});

it('authorName returns null for messages without author or agent', function () {
    $message = Message::factory()->fromUser()->create([
        'agent' => null,
        'author_type' => null,
        'author_id' => null,
    ]);

    expect($message->authorName())->toBeNull();
});

it('scopeActive filters to active messages only', function () {
    $conversation = Conversation::factory()->create();

    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'is_active' => true,
        'sequence' => 0,
    ]);
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'is_active' => true,
        'sequence' => 1,
    ]);
    Message::factory()->inactive()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 2,
    ]);

    expect(Message::where('conversation_id', $conversation->id)->active()->count())->toBe(2);
});

it('scopeDelivered filters delivered messages', function () {
    $conversation = Conversation::factory()->create();

    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'status' => MessageStatus::Delivered,
        'sequence' => 0,
    ]);
    Message::factory()->queued()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 1,
    ]);

    expect(Message::where('conversation_id', $conversation->id)->delivered()->count())->toBe(1);
});

it('scopeQueued filters queued messages', function () {
    $conversation = Conversation::factory()->create();

    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'status' => MessageStatus::Delivered,
        'sequence' => 0,
    ]);
    Message::factory()->queued()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 1,
    ]);
    Message::factory()->queued()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 2,
    ]);

    expect(Message::where('conversation_id', $conversation->id)->queued()->count())->toBe(2);
});

it('scopeByAuthor filters by polymorphic author', function () {
    $conversation = Conversation::factory()->create();
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'author_type' => 'App\\Models\\User',
        'author_id' => 5,
        'sequence' => 0,
    ]);
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'author_type' => 'App\\Models\\User',
        'author_id' => 10,
        'sequence' => 1,
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

    $results = Message::byAuthor($author)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->author_id)->toBe(5);
});

it('scopeByAgent filters by agent key', function () {
    $conversation = Conversation::factory()->create();
    Message::factory()->fromAssistant('agent-a')->create([
        'conversation_id' => $conversation->id,
        'sequence' => 0,
    ]);
    Message::factory()->fromAssistant('agent-b')->create([
        'conversation_id' => $conversation->id,
        'sequence' => 1,
    ]);

    $results = Message::byAgent('agent-a')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->agent)->toBe('agent-a');
});

it('siblings relationship returns messages with same parent', function () {
    $conversation = Conversation::factory()->create();
    $parent = Message::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 0,
    ]);
    $sibling1 = Message::factory()->fromAssistant('agent')->create([
        'conversation_id' => $conversation->id,
        'parent_id' => $parent->id,
        'sequence' => 1,
    ]);
    Message::factory()->fromAssistant('agent')->create([
        'conversation_id' => $conversation->id,
        'parent_id' => $parent->id,
        'sequence' => 2,
    ]);

    // siblings excludes self — only peer messages with the same parent
    expect($sibling1->siblings)->toHaveCount(1);
});
