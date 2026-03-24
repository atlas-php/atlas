<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\Role;
use Atlasphp\Atlas\Events\ConversationMessageStored;
use Atlasphp\Atlas\Persistence\Enums\MessageRole;
use Atlasphp\Atlas\Persistence\Http\StoreVoiceTranscriptController;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\Message;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

// ─── Helpers ─────────────────────────────────────────────────────

function invokeTranscriptController(string $sessionId, array $body): JsonResponse
{
    $controller = app(StoreVoiceTranscriptController::class);
    $request = Request::create("/voice/{$sessionId}/transcript", 'POST', $body);
    $request->setRouteResolver(fn () => null);

    return $controller($request, $sessionId);
}

// ─── Tests ──────────────────────────────────────────────────────

it('stores user and assistant turns as messages', function () {
    $conversation = Conversation::factory()->create();

    $response = invokeTranscriptController('sess-1', [
        'conversation_id' => $conversation->id,
        'turns' => [
            ['role' => 'user', 'transcript' => 'Hello there'],
            ['role' => 'assistant', 'transcript' => 'Hi! How can I help?'],
        ],
    ]);

    expect($response->getStatusCode())->toBe(200);

    $data = $response->getData(true);
    expect($data['stored'])->toHaveCount(2);

    $messages = Message::where('conversation_id', $conversation->id)
        ->orderBy('sequence')
        ->get();

    expect($messages)->toHaveCount(2);
    expect($messages[0]->role)->toBe(MessageRole::User);
    expect($messages[0]->content)->toBe('Hello there');
    expect($messages[1]->role)->toBe(MessageRole::Assistant);
    expect($messages[1]->content)->toBe('Hi! How can I help?');
});

it('marks all voice messages as read', function () {
    $conversation = Conversation::factory()->create();

    invokeTranscriptController('sess-2', [
        'conversation_id' => $conversation->id,
        'turns' => [
            ['role' => 'user', 'transcript' => 'Test'],
            ['role' => 'assistant', 'transcript' => 'Response'],
        ],
    ]);

    $messages = Message::where('conversation_id', $conversation->id)->get();

    foreach ($messages as $msg) {
        expect($msg->isRead())->toBeTrue();
    }
});

it('links assistant messages to preceding user message via parent_id', function () {
    $conversation = Conversation::factory()->create();

    invokeTranscriptController('sess-3', [
        'conversation_id' => $conversation->id,
        'turns' => [
            ['role' => 'user', 'transcript' => 'Question 1'],
            ['role' => 'assistant', 'transcript' => 'Answer 1'],
            ['role' => 'user', 'transcript' => 'Question 2'],
            ['role' => 'assistant', 'transcript' => 'Answer 2'],
        ],
    ]);

    $messages = Message::where('conversation_id', $conversation->id)
        ->orderBy('sequence')
        ->get();

    // First user message has no parent
    expect($messages[0]->parent_id)->toBeNull();
    // First assistant links to first user
    expect($messages[1]->parent_id)->toBe($messages[0]->id);
    // Second user has no parent
    expect($messages[2]->parent_id)->toBeNull();
    // Second assistant links to second user
    expect($messages[3]->parent_id)->toBe($messages[2]->id);
});

it('stores voice metadata with session_id', function () {
    $conversation = Conversation::factory()->create();

    invokeTranscriptController('sess-meta', [
        'conversation_id' => $conversation->id,
        'turns' => [
            ['role' => 'user', 'transcript' => 'Hello'],
        ],
    ]);

    $message = Message::where('conversation_id', $conversation->id)->first();

    expect($message->metadata['source'])->toBe('voice');
    expect($message->metadata['session_id'])->toBe('sess-meta');
});

it('dispatches ConversationMessageStored event for each turn', function () {
    Event::fake([ConversationMessageStored::class]);

    $conversation = Conversation::factory()->create();

    invokeTranscriptController('sess-events', [
        'conversation_id' => $conversation->id,
        'turns' => [
            ['role' => 'user', 'transcript' => 'Hello'],
            ['role' => 'assistant', 'transcript' => 'Hi'],
        ],
    ]);

    Event::assertDispatched(ConversationMessageStored::class, 2);

    Event::assertDispatched(function (ConversationMessageStored $event) use ($conversation) {
        return $event->conversationId === $conversation->id
            && $event->role === Role::User;
    });

    Event::assertDispatched(function (ConversationMessageStored $event) use ($conversation) {
        return $event->conversationId === $conversation->id
            && $event->role === Role::Assistant;
    });
});

it('sets agent on assistant messages only', function () {
    $conversation = Conversation::factory()->create();

    invokeTranscriptController('sess-agent', [
        'conversation_id' => $conversation->id,
        'agent' => 'support-bot',
        'turns' => [
            ['role' => 'user', 'transcript' => 'Hello'],
            ['role' => 'assistant', 'transcript' => 'Hi'],
        ],
    ]);

    $messages = Message::where('conversation_id', $conversation->id)
        ->orderBy('sequence')
        ->get();

    expect($messages[0]->agent)->toBeNull();
    expect($messages[1]->agent)->toBe('support-bot');
});

it('handles null author when author_type and author_id are not provided', function () {
    $conversation = Conversation::factory()->create();

    $response = invokeTranscriptController('sess-no-author', [
        'conversation_id' => $conversation->id,
        'turns' => [
            ['role' => 'user', 'transcript' => 'Hello'],
        ],
    ]);

    expect($response->getStatusCode())->toBe(200);

    $message = Message::where('conversation_id', $conversation->id)->first();
    expect($message->author_type)->toBeNull();
    expect($message->author_id)->toBeNull();
});

it('resolves author from morph map alias', function () {
    // Use a Conversation as the "owner" — it exists in the DB and has a known morph class
    Relation::morphMap(['transcript-owner' => Conversation::class]);

    // Create the owner record first, then the conversation
    $owner = Conversation::factory()->create();
    $conversation = Conversation::factory()->create();

    invokeTranscriptController('sess-morph', [
        'conversation_id' => $conversation->id,
        'author_type' => 'transcript-owner',
        'author_id' => $owner->id,
        'turns' => [
            ['role' => 'user', 'transcript' => 'Hello'],
            ['role' => 'assistant', 'transcript' => 'Hi'],
        ],
    ]);

    $messages = Message::where('conversation_id', $conversation->id)
        ->orderBy('sequence')
        ->get();

    // User message gets the author
    expect($messages[0]->author_type)->toBe('transcript-owner');
    expect((int) $messages[0]->author_id)->toBe($owner->id);

    // Assistant message does not get the author
    expect($messages[1]->author_type)->toBeNull();

    // Clean up morph map
    Relation::morphMap([], merge: false);
});

it('returns null author when morph type cannot be resolved', function () {
    $conversation = Conversation::factory()->create();

    $response = invokeTranscriptController('sess-bad-morph', [
        'conversation_id' => $conversation->id,
        'author_type' => 'nonexistent-type',
        'author_id' => 999,
        'turns' => [
            ['role' => 'user', 'transcript' => 'Hello'],
        ],
    ]);

    expect($response->getStatusCode())->toBe(200);

    $message = Message::where('conversation_id', $conversation->id)->first();
    expect($message->author_type)->toBeNull();
});

it('resolves author from fully qualified class name', function () {
    // Conversation is a Model subclass — use it as the author via its FQCN
    $owner = Conversation::factory()->create();
    $conversation = Conversation::factory()->create();

    invokeTranscriptController('sess-fqcn', [
        'conversation_id' => $conversation->id,
        'author_type' => Conversation::class,
        'author_id' => $owner->id,
        'turns' => [
            ['role' => 'user', 'transcript' => 'Hello'],
        ],
    ]);

    $message = Message::where('conversation_id', $conversation->id)->first();

    expect($message->author_type)->not->toBeNull();
    expect((int) $message->author_id)->toBe($owner->id);
});
