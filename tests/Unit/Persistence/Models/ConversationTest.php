<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Enums\MessageStatus;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\ConversationMessage;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Illuminate\Database\Eloquent\Model;

it('creates a valid record via factory', function () {
    $conversation = Conversation::factory()->create();

    expect($conversation)->toBeInstanceOf(Conversation::class)
        ->and($conversation->exists)->toBeTrue()
        ->and($conversation->agent)->toBeString()
        ->and($conversation->title)->toBeString();
});

it('recentMessages returns only active delivered messages', function () {
    $conversation = Conversation::factory()->create();

    // Active + delivered — should be included
    $delivered = collect();
    for ($i = 0; $i < 3; $i++) {
        $delivered->push(ConversationMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'is_active' => true,
            'status' => MessageStatus::Delivered,
            'sequence' => $conversation->nextSequence(),
        ]));
    }

    // Inactive — should be excluded
    ConversationMessage::factory()->inactive()->create([
        'conversation_id' => $conversation->id,
        'status' => MessageStatus::Delivered,
        'sequence' => $conversation->nextSequence(),
    ]);

    // Queued — should be excluded
    ConversationMessage::factory()->queued()->create([
        'conversation_id' => $conversation->id,
        'is_active' => true,
        'sequence' => $conversation->nextSequence(),
    ]);

    $recent = $conversation->recentMessages();

    expect($recent)->toHaveCount(3)
        ->and($recent->pluck('id')->sort()->values()->toArray())
        ->toEqual($delivered->pluck('id')->sort()->values()->toArray());
});

it('nextSequence returns the correct next value', function () {
    $conversation = Conversation::factory()->create();

    expect($conversation->nextSequence())->toBe(1);

    ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 1,
    ]);

    expect($conversation->nextSequence())->toBe(2);

    ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 6,
    ]);

    expect($conversation->nextSequence())->toBe(7);
});

it('scopeForAgent filters by agent', function () {
    Conversation::factory()->withAgent('writer')->create();
    Conversation::factory()->withAgent('writer')->create();
    Conversation::factory()->withAgent('coder')->create();

    expect(Conversation::forAgent('writer')->count())->toBe(2)
        ->and(Conversation::forAgent('coder')->count())->toBe(1);
});

it('supports soft deletes', function () {
    $conversation = Conversation::factory()->create();

    $conversation->delete();

    expect($conversation->trashed())->toBeTrue()
        ->and(Conversation::count())->toBe(0)
        ->and(Conversation::withTrashed()->count())->toBe(1);

    $conversation->restore();

    expect($conversation->trashed())->toBeFalse()
        ->and(Conversation::count())->toBe(1);
});

it('messages relationship returns ordered by sequence', function () {
    $conversation = Conversation::factory()->create();

    ConversationMessage::factory()->create(['conversation_id' => $conversation->id, 'sequence' => 3]);
    ConversationMessage::factory()->create(['conversation_id' => $conversation->id, 'sequence' => 1]);
    ConversationMessage::factory()->create(['conversation_id' => $conversation->id, 'sequence' => 2]);

    $messages = $conversation->messages;

    expect($messages->pluck('sequence')->toArray())->toBe([1, 2, 3]);
});

it('executions relationship returns related executions', function () {
    $conversation = Conversation::factory()->create();

    Execution::factory()->count(2)->create(['conversation_id' => $conversation->id]);
    Execution::factory()->create(); // unrelated

    expect($conversation->executions)->toHaveCount(2);
});

it('scopeForOwner filters by polymorphic owner', function () {
    Conversation::factory()->create(['owner_type' => 'App\\Models\\User', 'owner_id' => 1]);
    Conversation::factory()->create(['owner_type' => 'App\\Models\\User', 'owner_id' => 2]);
    Conversation::factory()->create(['owner_type' => 'App\\Models\\Team', 'owner_id' => 1]);

    $owner = new class extends Model
    {
        protected $table = 'users';

        public function getMorphClass(): string
        {
            return 'App\\Models\\User';
        }

        public function getKey(): mixed
        {
            return 1;
        }
    };

    $results = Conversation::forOwner($owner)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->owner_id)->toBe(1)
        ->and($results->first()->owner_type)->toBe('App\\Models\\User');
});

it('getTable does not double-prefix when table already has prefix', function () {
    $conversation = Conversation::factory()->create();

    // The table should be 'atlas_conversations', not 'atlas_atlas_conversations'
    expect($conversation->getTable())->toBe('atlas_conversations');

    // Call getTable() twice to ensure idempotency
    expect($conversation->getTable())->toBe('atlas_conversations');
});
