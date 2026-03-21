<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Enums\MessageStatus;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\Message;

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
        $delivered->push(Message::factory()->create([
            'conversation_id' => $conversation->id,
            'is_active' => true,
            'status' => MessageStatus::Delivered,
            'sequence' => $conversation->nextSequence(),
        ]));
    }

    // Inactive — should be excluded
    Message::factory()->inactive()->create([
        'conversation_id' => $conversation->id,
        'status' => MessageStatus::Delivered,
        'sequence' => $conversation->nextSequence(),
    ]);

    // Queued — should be excluded
    Message::factory()->queued()->create([
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

    expect($conversation->nextSequence())->toBe(0);

    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 0,
    ]);

    expect($conversation->nextSequence())->toBe(1);

    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'sequence' => 5,
    ]);

    expect($conversation->nextSequence())->toBe(6);
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

    Message::factory()->create(['conversation_id' => $conversation->id, 'sequence' => 2]);
    Message::factory()->create(['conversation_id' => $conversation->id, 'sequence' => 0]);
    Message::factory()->create(['conversation_id' => $conversation->id, 'sequence' => 1]);

    $messages = $conversation->messages;

    expect($messages->pluck('sequence')->toArray())->toBe([0, 1, 2]);
});

it('executions relationship returns related executions', function () {
    $conversation = Conversation::factory()->create();

    Execution::factory()->count(2)->create(['conversation_id' => $conversation->id]);
    Execution::factory()->create(); // unrelated

    expect($conversation->executions)->toHaveCount(2);
});
