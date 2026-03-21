<?php

declare(strict_types=1);

use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\SystemMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Persistence\Enums\MessageRole;
use Atlasphp\Atlas\Persistence\Enums\MessageStatus;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\ExecutionStep;
use Atlasphp\Atlas\Persistence\Models\Message;
use Atlasphp\Atlas\Persistence\Services\ConversationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(function () {
    $this->service = new ConversationService;
});

// ─── findOrCreate ──────────────────────────────────────────────────

it('creates a new conversation for an owner and agent combo', function () {
    $owner = createMockOwner(1);

    $conversation = $this->service->findOrCreate($owner, 'test-agent');

    expect($conversation)->toBeInstanceOf(Conversation::class)
        ->and($conversation->exists)->toBeTrue()
        ->and($conversation->owner_type)->toBe($owner->getMorphClass())
        ->and($conversation->owner_id)->toBe(1)
        ->and($conversation->agent)->toBe('test-agent');
});

it('findOrCreate is idempotent — second call returns same conversation', function () {
    $owner = createMockOwner(1);

    $first = $this->service->findOrCreate($owner, 'test-agent');
    $second = $this->service->findOrCreate($owner, 'test-agent');

    expect($first->id)->toBe($second->id)
        ->and(Conversation::count())->toBe(1);
});

// ─── find ──────────────────────────────────────────────────────────

it('finds an existing conversation by ID', function () {
    $conversation = Conversation::factory()->create();

    $found = $this->service->find($conversation->id);

    expect($found->id)->toBe($conversation->id);
});

it('throws ModelNotFoundException for non-existent ID', function () {
    $this->service->find(99999);
})->throws(ModelNotFoundException::class);

// ─── addMessage ────────────────────────────────────────────────────

it('stores a user message with correct role, content, and sequence', function () {
    $conversation = Conversation::factory()->create();

    $stored = $this->service->addMessage(
        $conversation,
        new UserMessage(content: 'Hello, world!'),
    );

    expect($stored)->toBeInstanceOf(Message::class)
        ->and($stored->role)->toBe(MessageRole::User)
        ->and($stored->content)->toBe('Hello, world!')
        ->and($stored->sequence)->toBe(0)
        ->and($stored->is_active)->toBeTrue()
        ->and($stored->status)->toBe(MessageStatus::Delivered);
});

it('stores assistant message with agent key', function () {
    $conversation = Conversation::factory()->create();

    $stored = $this->service->addMessage(
        $conversation,
        new AssistantMessage(content: 'I can help with that.'),
        agent: 'writer-agent',
    );

    expect($stored->role)->toBe(MessageRole::Assistant)
        ->and($stored->content)->toBe('I can help with that.')
        ->and($stored->agent)->toBe('writer-agent');
});

// ─── addAssistantMessages ──────────────────────────────────────────

it('stores one message per step with correct step_id and incrementing sequences', function () {
    $conversation = Conversation::factory()->create();
    $execution = Execution::factory()->create(['conversation_id' => $conversation->id]);
    $step1 = ExecutionStep::factory()->create(['execution_id' => $execution->id, 'sequence' => 0]);
    $step2 = ExecutionStep::factory()->create(['execution_id' => $execution->id, 'sequence' => 1]);

    $stored = $this->service->addAssistantMessages(
        $conversation,
        [
            ['text' => 'Step one response', 'step_id' => $step1->id],
            ['text' => 'Step two response', 'step_id' => $step2->id],
        ],
        agent: 'test-agent',
        parentId: null,
    );

    expect($stored)->toHaveCount(2)
        ->and($stored[0]->content)->toBe('Step one response')
        ->and($stored[0]->step_id)->toBe($step1->id)
        ->and($stored[0]->role)->toBe(MessageRole::Assistant)
        ->and($stored[1]->content)->toBe('Step two response')
        ->and($stored[1]->step_id)->toBe($step2->id)
        ->and($stored[1]->sequence)->toBeGreaterThan($stored[0]->sequence);
});

// ─── loadMessages ──────────────────────────────────────────────────

it('returns Atlas typed messages for each role', function () {
    $conversation = Conversation::factory()->create();

    Message::factory()->system()->create([
        'conversation_id' => $conversation->id,
        'content' => 'You are a helpful assistant.',
        'sequence' => 0,
    ]);
    Message::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'content' => 'Hello!',
        'sequence' => 1,
    ]);
    Message::factory()->fromAssistant('bot')->create([
        'conversation_id' => $conversation->id,
        'content' => 'Hi there!',
        'sequence' => 2,
    ]);

    $messages = $this->service->loadMessages($conversation);

    expect($messages)->toHaveCount(3);

    // Verify all three message types are present with correct content.
    // loadMessages returns messages ordered by sequence via the relationship,
    // reversed by the collection, yielding newest-first due to the dual
    // ORDER BY (relationship ASC + latest DESC) in SQLite.
    $types = array_map(fn ($m) => get_class($m), $messages);

    expect($types)->toContain(SystemMessage::class)
        ->toContain(UserMessage::class)
        ->toContain(AssistantMessage::class);

    $byType = [];
    foreach ($messages as $msg) {
        $byType[get_class($msg)] = $msg;
    }

    expect($byType[SystemMessage::class]->content)->toBe('You are a helpful assistant.')
        ->and($byType[UserMessage::class]->content)->toBe('Hello!')
        ->and($byType[AssistantMessage::class]->content)->toBe('Hi there!');
});

it('excludes queued and inactive messages from loadMessages', function () {
    $conversation = Conversation::factory()->create();

    Message::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'content' => 'Active delivered',
        'sequence' => 0,
    ]);
    Message::factory()->fromUser()->queued()->create([
        'conversation_id' => $conversation->id,
        'content' => 'Queued message',
        'sequence' => 1,
    ]);
    Message::factory()->fromUser()->inactive()->create([
        'conversation_id' => $conversation->id,
        'content' => 'Inactive message',
        'sequence' => 2,
    ]);

    $messages = $this->service->loadMessages($conversation);

    expect($messages)->toHaveCount(1)
        ->and($messages[0]->content)->toBe('Active delivered');
});

// ─── prepareRetry ──────────────────────────────────────────────────

it('deactivates current active group and returns parentId', function () {
    $conversation = Conversation::factory()->create();

    // User message (parent)
    $userMsg = Message::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 0,
    ]);

    // Assistant response linked to user message
    $assistantMsg = Message::factory()->fromAssistant('bot')->create([
        'conversation_id' => $conversation->id,
        'parent_id' => $userMsg->id,
        'sequence' => 1,
    ]);

    $parentId = $this->service->prepareRetry($conversation);

    expect($parentId)->toBe($userMsg->id);

    // The assistant message should now be inactive
    $assistantMsg->refresh();
    expect($assistantMsg->is_active)->toBeFalse();
});

// ─── markAsRead ────────────────────────────────────────────────────

it('marks messages as read up to a sequence', function () {
    $conversation = Conversation::factory()->create();

    Message::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 0,
    ]);
    Message::factory()->fromAssistant('bot')->create([
        'conversation_id' => $conversation->id,
        'sequence' => 1,
    ]);
    Message::factory()->fromAssistant('bot')->create([
        'conversation_id' => $conversation->id,
        'sequence' => 2,
    ]);

    $count = $this->service->markAsRead($conversation, upToSequence: 1);

    expect($count)->toBe(2);

    // Message at sequence 2 should still be unread
    $unread = Message::where('conversation_id', $conversation->id)
        ->whereNull('read_at')
        ->count();

    expect($unread)->toBe(1);
});

// ─── unreadCount ───────────────────────────────────────────────────

it('returns correct unread count', function () {
    $conversation = Conversation::factory()->create();

    Message::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 0,
    ]);
    Message::factory()->fromUser()->read()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 1,
    ]);
    Message::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 2,
    ]);

    expect($this->service->unreadCount($conversation))->toBe(2);
});

// ─── queueMessage ──────────────────────────────────────────────────

it('stores message with queued status', function () {
    $conversation = Conversation::factory()->create();

    $stored = $this->service->queueMessage(
        $conversation,
        new UserMessage(content: 'Queued question'),
    );

    expect($stored->status)->toBe(MessageStatus::Queued)
        ->and($stored->content)->toBe('Queued question')
        ->and($stored->is_active)->toBeTrue();
});

// ─── hasActiveExecution ────────────────────────────────────────────

it('returns true when a processing execution exists', function () {
    $conversation = Conversation::factory()->create();

    Execution::factory()->processing()->create([
        'conversation_id' => $conversation->id,
    ]);

    expect($this->service->hasActiveExecution($conversation))->toBeTrue();
});

it('returns false when no processing execution exists', function () {
    $conversation = Conversation::factory()->create();

    Execution::factory()->completed()->create([
        'conversation_id' => $conversation->id,
    ]);

    expect($this->service->hasActiveExecution($conversation))->toBeFalse();
});

// ─── nextQueuedMessage ─────────────────────────────────────────────

it('returns lowest-sequence queued message', function () {
    $conversation = Conversation::factory()->create();

    Message::factory()->fromUser()->queued()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 5,
    ]);
    $first = Message::factory()->fromUser()->queued()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 3,
    ]);

    $next = $this->service->nextQueuedMessage($conversation);

    expect($next)->not->toBeNull()
        ->and($next->id)->toBe($first->id);
});

// ─── deliverNextQueued ─────────────────────────────────────────────

it('transitions message from queued to delivered', function () {
    $conversation = Conversation::factory()->create();

    Message::factory()->fromUser()->queued()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 0,
    ]);

    $delivered = $this->service->deliverNextQueued($conversation);

    expect($delivered)->not->toBeNull()
        ->and($delivered->status)->toBe(MessageStatus::Delivered);
});

it('returns null when no queued messages exist', function () {
    $conversation = Conversation::factory()->create();

    expect($this->service->deliverNextQueued($conversation))->toBeNull();
});

// ─── lastUserMessageId ─────────────────────────────────────────────

it('returns the last active user message ID', function () {
    $conversation = Conversation::factory()->create();

    Message::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 0,
    ]);
    $last = Message::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 1,
    ]);
    Message::factory()->fromAssistant('bot')->create([
        'conversation_id' => $conversation->id,
        'sequence' => 2,
    ]);

    expect($this->service->lastUserMessageId($conversation))->toBe($last->id);
});

it('returns null when no user messages exist', function () {
    $conversation = Conversation::factory()->create();

    expect($this->service->lastUserMessageId($conversation))->toBeNull();
});

// ─── Helper ────────────────────────────────────────────────────────

function createMockOwner(int $id): Model
{
    return new class($id) extends Model
    {
        protected $table = 'atlas_conversations';

        public function __construct(int $id = 1)
        {
            parent::__construct();
            $this->id = $id;
            $this->exists = true;
        }

        public function getMorphClass(): string
        {
            return 'test-owner';
        }

        public function getKey(): mixed
        {
            return $this->id;
        }
    };
}
