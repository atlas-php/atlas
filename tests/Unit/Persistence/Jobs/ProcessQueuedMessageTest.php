<?php

declare(strict_types=1);

use Atlasphp\Atlas\Messages\UserMessage;
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

it('reads tries from atlas.queue config', function () {
    config(['atlas.queue.tries' => 5]);

    $job = new ProcessQueuedMessage(conversationId: 1, agentKey: 'test');

    expect($job->tries)->toBe(5);
});

it('reads backoff from atlas.queue config', function () {
    config(['atlas.queue.backoff' => 60]);

    $job = new ProcessQueuedMessage(conversationId: 1, agentKey: 'test');

    expect($job->backoff)->toBe(60);
});

it('reads timeout from atlas.queue config', function () {
    config(['atlas.queue.timeout' => 600]);

    $job = new ProcessQueuedMessage(conversationId: 1, agentKey: 'test');

    expect($job->timeout)->toBe(600);
});

it('returns early when queue is empty', function () {
    $conversation = Conversation::factory()->create();

    $job = new ProcessQueuedMessage(
        conversationId: $conversation->id,
        agentKey: 'test-agent',
    );

    $conversations = app(ConversationService::class);
    $job->handle($conversations);

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

    // Atlas::agent() will throw because agent execution is not yet implemented.
    // The catch block should re-queue the message.
    try {
        $job->handle($conversations);
    } catch (Throwable) {
        // Expected — the agent call fails
    }

    $message->refresh();

    expect($message->status)->toBe(MessageStatus::Queued);
});

it('marks message as failed when all retries exhausted', function () {
    $conversation = Conversation::factory()->create();

    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'content' => 'Will fail permanently',
        'status' => MessageStatus::Delivered,
        'sequence' => 0,
    ]);

    $job = new ProcessQueuedMessage(
        conversationId: $conversation->id,
        agentKey: 'test-agent',
    );

    // Simulate the job having delivered this message
    $reflection = new ReflectionProperty($job, 'deliveredMessageId');
    $reflection->setValue($job, $message->id);

    $job->failed(new RuntimeException('Provider error'));

    $message->refresh();

    expect($message->status)->toBe(MessageStatus::Failed);
});

it('stores request context in metadata when queueing', function () {
    $conversation = Conversation::factory()->create();
    $conversations = app(ConversationService::class);

    $message = $conversations->queueMessage(
        $conversation,
        new UserMessage('Hello'),
        requestContext: [
            'variables' => ['USER_NAME' => 'Tim'],
            'meta' => ['user_id' => 123],
            'provider_options' => ['temperature' => 0.5],
        ],
    );

    expect($message->status)->toBe(MessageStatus::Queued);
    expect($message->metadata)->toBe([
        'variables' => ['USER_NAME' => 'Tim'],
        'meta' => ['user_id' => 123],
        'provider_options' => ['temperature' => 0.5],
    ]);
});
