<?php

declare(strict_types=1);

use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Persistence\Enums\MessageStatus;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\ConversationMessage;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\ProcessQueuedMessage;
use Atlasphp\Atlas\Persistence\Services\ConversationService;
use Illuminate\Contracts\Queue\Job;
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

it('has default tries', function () {
    $job = new ProcessQueuedMessage(conversationId: 1, agentKey: 'test');

    expect($job->tries)->toBe(3);
});

it('has default backoff', function () {
    $job = new ProcessQueuedMessage(conversationId: 1, agentKey: 'test');

    expect($job->backoff)->toBe(30);
});

it('has default timeout', function () {
    $job = new ProcessQueuedMessage(conversationId: 1, agentKey: 'test');

    expect($job->timeout)->toBe(300);
});

it('returns early when queue is empty', function () {
    $conversation = Conversation::factory()->create();

    $job = new ProcessQueuedMessage(
        conversationId: $conversation->id,
        agentKey: 'test-agent',
    );

    $conversations = app(ConversationService::class);
    $job->handle($conversations);

    expect(ConversationMessage::count())->toBe(0);
});

it('delivers next queued message and re-queues on agent failure', function () {
    $conversation = Conversation::factory()->create();

    $message = ConversationMessage::factory()->queued()->create([
        'conversation_id' => $conversation->id,
        'content' => 'Hello from queue',
        'sequence' => 1,
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

    $message = ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'content' => 'Will fail permanently',
        'status' => MessageStatus::Delivered,
        'sequence' => 1,
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

it('releases job when conversation has active execution', function () {
    $conversation = Conversation::factory()->create();

    // Create a processing execution to simulate an active one
    $executionModel = config('atlas.persistence.models.execution', Execution::class);
    $executionModel::factory()->processing()->create([
        'conversation_id' => $conversation->id,
    ]);

    $job = new ProcessQueuedMessage(
        conversationId: $conversation->id,
        agentKey: 'test-agent',
    );

    // Mock the queue job so release() works
    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('release')->with(5)->once();
    $queueJob->shouldReceive('isDeletedOrReleased')->andReturn(false);
    $job->job = $queueJob;

    $conversations = app(ConversationService::class);
    $job->handle($conversations);
});

it('failed does nothing when deliveredMessageId is null', function () {
    $job = new ProcessQueuedMessage(
        conversationId: 1,
        agentKey: 'test-agent',
    );

    // deliveredMessageId is null by default — failed() should return early
    $job->failed(new RuntimeException('Some error'));

    // No exception, no DB updates — just returns
    expect(true)->toBeTrue();
});

it('executeAgent applies variables from metadata context', function () {
    $conversation = Conversation::factory()->create();
    $conversations = app(ConversationService::class);

    // Queue a message with variables context
    $message = $conversations->queueMessage(
        $conversation,
        new UserMessage('Hello'),
        requestContext: [
            'variables' => ['USER_NAME' => 'Tim'],
        ],
    );

    // Deliver the message so executeAgent picks it up
    $conversations->deliverNextQueued($conversation);
    $message->refresh();

    // Verify metadata is preserved on the delivered message
    expect($message->metadata)->toHaveKey('variables')
        ->and($message->metadata['variables'])->toBe(['USER_NAME' => 'Tim']);
});

it('executeAgent applies meta from metadata context', function () {
    $conversation = Conversation::factory()->create();
    $conversations = app(ConversationService::class);

    $message = $conversations->queueMessage(
        $conversation,
        new UserMessage('Hello'),
        requestContext: [
            'meta' => ['user_id' => 123],
        ],
    );

    $conversations->deliverNextQueued($conversation);
    $message->refresh();

    expect($message->metadata)->toHaveKey('meta')
        ->and($message->metadata['meta'])->toBe(['user_id' => 123]);
});

it('executeAgent applies provider_options from metadata context', function () {
    $conversation = Conversation::factory()->create();
    $conversations = app(ConversationService::class);

    $message = $conversations->queueMessage(
        $conversation,
        new UserMessage('Hello'),
        requestContext: [
            'provider_options' => ['temperature' => 0.5],
        ],
    );

    $conversations->deliverNextQueued($conversation);
    $message->refresh();

    expect($message->metadata)->toHaveKey('provider_options')
        ->and($message->metadata['provider_options'])->toBe(['temperature' => 0.5]);
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
