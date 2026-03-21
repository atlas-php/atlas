<?php

declare(strict_types=1);

use Atlasphp\Atlas\Persistence\Enums\MessageStatus;
use Atlasphp\Atlas\Persistence\Jobs\ProcessQueuedMessage;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\Message;
use Atlasphp\Atlas\Persistence\Services\ConversationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;

it('stores correct properties', function () {
    $job = new ProcessQueuedMessage(
        conversationId: 42,
        agentKey: 'writer',
    );

    expect($job->conversationId)->toBe(42)
        ->and($job->agentKey)->toBe('writer');
});

it('implements ShouldQueue and ShouldBeUnique', function () {
    $job = new ProcessQueuedMessage(conversationId: 1, agentKey: 'test');

    expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->and($job)->toBeInstanceOf(ShouldBeUnique::class);
});

it('uniqueId returns conversation-scoped string', function () {
    $job = new ProcessQueuedMessage(conversationId: 99, agentKey: 'writer');

    expect($job->uniqueId())->toBe('atlas-queued-99');
});

it('has tries set to 3', function () {
    $job = new ProcessQueuedMessage(conversationId: 1, agentKey: 'test');

    expect($job->tries)->toBe(3);
});

it('returns early when queue is empty', function () {
    $conversation = Conversation::factory()->create();

    // No queued messages exist
    $job = new ProcessQueuedMessage(
        conversationId: $conversation->id,
        agentKey: 'test-agent',
    );

    $conversations = app(ConversationService::class);
    $job->handle($conversations);

    // No messages exist at all, so nothing should have changed
    expect(Message::count())->toBe(0);
});

it('delivers next queued message and re-queues on agent failure', function () {
    $conversation = Conversation::factory()->create();

    $message = Message::factory()->queued()->create([
        'conversation_id' => $conversation->id,
        'content' => 'Hello from queue',
        'sequence' => 0,
    ]);

    $job = new ProcessQueuedMessage(
        conversationId: $conversation->id,
        agentKey: 'nonexistent-agent',
    );

    $conversations = app(ConversationService::class);

    // Atlas::agent() will throw because 'nonexistent-agent' is not registered.
    // The catch block should re-queue the message.
    try {
        $job->handle($conversations);
    } catch (Throwable) {
        // Expected — the agent call fails
    }

    $message->refresh();

    // Message should be back to Queued after the catch block
    expect($message->status)->toBe(MessageStatus::Queued);
});
