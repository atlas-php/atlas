<?php

declare(strict_types=1);

use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Messages\ToolResultMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Persistence\Enums\MessageRole;
use Atlasphp\Atlas\Persistence\Enums\MessageStatus;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\Message;

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
