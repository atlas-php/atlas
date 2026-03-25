<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Enums\Role;
use Atlasphp\Atlas\Events\ConversationMessageStored;
use Atlasphp\Atlas\Executor\ExecutorResult;
use Atlasphp\Atlas\Executor\Step;
use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Middleware\AgentContext;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;
use Atlasphp\Atlas\Persistence\Enums\AssetType;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Enums\MessageRole;
use Atlasphp\Atlas\Persistence\Enums\MessageStatus;
use Atlasphp\Atlas\Persistence\Middleware\PersistConversation;
use Atlasphp\Atlas\Persistence\Models\Asset;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\Message;
use Atlasphp\Atlas\Persistence\Models\MessageAttachment;
use Atlasphp\Atlas\Persistence\ProcessQueuedMessage;
use Atlasphp\Atlas\Persistence\Services\ConversationService;
use Atlasphp\Atlas\Persistence\Services\ExecutionService;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function makeTestAgent(): Agent
{
    return new class extends Agent
    {
        use HasConversations;

        public function key(): string
        {
            return 'test-agent';
        }
    };
}

function makePlainAgent(): Agent
{
    return new class extends Agent
    {
        public function key(): string
        {
            return 'plain-agent';
        }
    };
}

function makePersistConversationRequest(): TextRequest
{
    return new TextRequest(
        model: 'gpt-5',
        instructions: null,
        message: 'Hello',
        messageMedia: [],
        messages: [],
        maxTokens: null,
        temperature: null,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );
}

function makeFakeExecutorResult(): ExecutorResult
{
    return new ExecutorResult(
        text: 'Test response',
        reasoning: null,
        steps: [],
        usage: new Usage(10, 5),
        finishReason: FinishReason::Stop,
        meta: [],
    );
}

function makeFakeExecutorResultWithSteps(): ExecutorResult
{
    return new ExecutorResult(
        text: 'Test response',
        reasoning: null,
        steps: [
            new Step(
                text: 'Step one response',
                toolCalls: [],
                toolResults: [],
                usage: new Usage(5, 3),
            ),
            new Step(
                text: 'Step two response',
                toolCalls: [],
                toolResults: [],
                usage: new Usage(5, 2),
            ),
        ],
        usage: new Usage(10, 5),
        finishReason: FinishReason::Stop,
        meta: [],
    );
}

it('passes through when agent is null', function () {
    $middleware = app(PersistConversation::class);

    $context = new AgentContext(
        request: makePersistConversationRequest(),
        agent: null,
        meta: [],
    );

    $called = false;
    $expected = makeFakeExecutorResult();

    $result = $middleware->handle($context, function () use (&$called, $expected) {
        $called = true;

        return $expected;
    });

    expect($called)->toBeTrue()
        ->and($result)->toBe($expected);
});

it('passes through when agent does not use HasConversations', function () {
    $middleware = app(PersistConversation::class);

    $context = new AgentContext(
        request: makePersistConversationRequest(),
        agent: makePlainAgent(),
        meta: [],
    );

    $called = false;
    $expected = makeFakeExecutorResult();

    $result = $middleware->handle($context, function () use (&$called, $expected) {
        $called = true;

        return $expected;
    });

    expect($called)->toBeTrue()
        ->and($result)->toBe($expected);
});

it('passes through when no conversation configured', function () {
    $middleware = app(PersistConversation::class);

    $agent = makeTestAgent();

    $context = new AgentContext(
        request: makePersistConversationRequest(),
        agent: $agent,
        meta: [],
    );

    $called = false;
    $expected = makeFakeExecutorResult();

    $result = $middleware->handle($context, function () use (&$called, $expected) {
        $called = true;

        return $expected;
    });

    expect($called)->toBeTrue()
        ->and($result)->toBe($expected);
});

it('injects conversation_id into context meta', function () {
    $middleware = app(PersistConversation::class);

    $conversation = Conversation::create([
        'agent' => 'test-agent',
    ]);

    $agent = makeTestAgent();
    $agent->forConversation($conversation->id);

    $context = new AgentContext(
        request: makePersistConversationRequest(),
        agent: $agent,
        meta: [],
    );

    $middleware->handle($context, fn () => makeFakeExecutorResult());

    expect($context->meta['conversation_id'])->toBe($conversation->id);
});

it('links assistant response to pre-stored user message', function () {
    $middleware = app(PersistConversation::class);

    $conversation = Conversation::create([
        'agent' => 'test-agent',
    ]);

    // User message is pre-stored by AgentRequest (before dispatch)
    $conversations = app(ConversationService::class);
    $userMsg = $conversations->addMessage(
        $conversation,
        new UserMessage(content: 'Hello'),
    );

    $agent = makeTestAgent();
    $agent->forConversation($conversation->id);
    $agent->respond();

    $context = new AgentContext(
        request: makePersistConversationRequest(),
        agent: $agent,
        messages: [],
        meta: [],
    );

    $middleware->handle($context, fn () => makeFakeExecutorResult());

    $assistantMessage = Message::where('conversation_id', $conversation->id)
        ->where('role', MessageRole::Assistant)
        ->first();

    expect($assistantMessage)->not->toBeNull()
        ->and($assistantMessage->parent_id)->toBe($userMsg->id);
});

it('throws when respond mode used without forConversation', function () {
    $middleware = app(PersistConversation::class);

    $agent = makeTestAgent();
    $agent->respond();

    $context = new AgentContext(
        request: makePersistConversationRequest(),
        agent: $agent,
        meta: [],
    );

    $middleware->handle($context, fn () => makeFakeExecutorResult());
})->throws(RuntimeException::class, 'respond() and retry() require forConversation($id).');

it('stores assistant messages from steps', function () {
    $middleware = app(PersistConversation::class);

    $conversation = Conversation::create([
        'agent' => 'test-agent',
    ]);

    $agent = makeTestAgent();
    $agent->forConversation($conversation->id);

    $context = new AgentContext(
        request: makePersistConversationRequest(),
        agent: $agent,
        messages: [new UserMessage(content: 'Hello')],
        meta: [],
    );

    $middleware->handle($context, fn () => makeFakeExecutorResultWithSteps());

    $assistantMessages = Message::where('conversation_id', $conversation->id)
        ->where('role', MessageRole::Assistant)
        ->orderBy('sequence')
        ->get();

    // Only the final response text is stored as a message
    expect($assistantMessages)->toHaveCount(1)
        ->and($assistantMessages[0]->content)->toBe('Test response')
        ->and($assistantMessages[0]->agent)->toBe('test-agent');
});

// ─── Retry mode ────────────────────────────────────────────────────

it('deactivates current response and sets retryParentId in retry mode', function () {
    $middleware = app(PersistConversation::class);

    $conversation = Conversation::create(['agent' => 'test-agent']);

    // Set up existing user + assistant messages
    $userMsg = Message::create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::User,
        'content' => 'Question',
        'sequence' => 0,
        'is_active' => true,
    ]);

    Message::create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Assistant,
        'content' => 'Old answer',
        'agent' => 'test-agent',
        'sequence' => 1,
        'is_active' => true,
        'parent_id' => $userMsg->id,
    ]);

    $agent = makeTestAgent();
    $agent->forConversation($conversation->id);
    $agent->retry();

    $context = new AgentContext(
        request: makePersistConversationRequest(),
        agent: $agent,
        messages: [],
        meta: [],
    );

    $middleware->handle($context, fn () => makeFakeExecutorResultWithSteps());

    // Old response should be deactivated
    $oldMsg = Message::where('content', 'Old answer')->first();
    expect($oldMsg->is_active)->toBeFalse();

    // New response should be active with same parent_id
    $newMsgs = Message::where('conversation_id', $conversation->id)
        ->where('role', MessageRole::Assistant)
        ->where('is_active', true)
        ->get();

    // Only the final response text is stored as a message
    expect($newMsgs)->toHaveCount(1);
    expect($newMsgs->first()->parent_id)->toBe($userMsg->id);
});

it('throws when retry mode used without forConversation', function () {
    $middleware = app(PersistConversation::class);

    $agent = makeTestAgent();
    $agent->retry();

    $context = new AgentContext(
        request: makePersistConversationRequest(),
        agent: $agent,
        meta: [],
    );

    $middleware->handle($context, fn () => makeFakeExecutorResult());
})->throws(RuntimeException::class, 'respond() and retry() require forConversation($id).');

// ─── History merging ───────────────────────────────────────────────

it('prepends conversation history to context messages', function () {
    $middleware = app(PersistConversation::class);

    $conversation = Conversation::create(['agent' => 'test-agent']);

    // Existing message in conversation
    Message::create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::User,
        'content' => 'Previous message',
        'sequence' => 0,
        'is_active' => true,
    ]);

    $agent = makeTestAgent();
    $agent->forConversation($conversation->id);

    $newMessage = new UserMessage(content: 'New message');

    $capturedMessages = null;

    $context = new AgentContext(
        request: makePersistConversationRequest(),
        agent: $agent,
        messages: [$newMessage],
        meta: [],
    );

    $middleware->handle($context, function (AgentContext $ctx) use (&$capturedMessages) {
        $capturedMessages = $ctx->messages;

        return makeFakeExecutorResult();
    });

    // History should be prepended — previous message first, then new message.
    // loadMessages remaps roles for group conversations (agent key is passed),
    // so the previous user message gets an "[Unknown]: " prefix.
    expect($capturedMessages)->toHaveCount(2)
        ->and($capturedMessages[0]->content)->toContain('Previous message')
        ->and($capturedMessages[1]->content)->toBe('New message');
});

// ─── Queued message dispatch ───────────────────────────────────────

it('dispatches ProcessQueuedMessage when queued messages exist', function () {
    Queue::fake();

    $middleware = app(PersistConversation::class);

    $conversation = Conversation::create(['agent' => 'test-agent']);

    // Create a queued message waiting to be processed
    Message::create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::User,
        'content' => 'Queued question',
        'sequence' => 0,
        'is_active' => true,
        'status' => MessageStatus::Queued,
    ]);

    $agent = makeTestAgent();
    $agent->forConversation($conversation->id);

    $context = new AgentContext(
        request: makePersistConversationRequest(),
        agent: $agent,
        messages: [new UserMessage(content: 'Current message')],
        meta: [],
    );

    $middleware->handle($context, fn () => makeFakeExecutorResult());

    Queue::assertPushed(ProcessQueuedMessage::class, function ($job) use ($conversation) {
        return $job->conversationId === $conversation->id
            && $job->agentKey === 'test-agent';
    });
});

it('does not dispatch ProcessQueuedMessage when no queued messages exist', function () {
    Queue::fake();

    $middleware = app(PersistConversation::class);

    $conversation = Conversation::create(['agent' => 'test-agent']);

    $agent = makeTestAgent();
    $agent->forConversation($conversation->id);

    $context = new AgentContext(
        request: makePersistConversationRequest(),
        agent: $agent,
        messages: [new UserMessage(content: 'Hello')],
        meta: [],
    );

    $middleware->handle($context, fn () => makeFakeExecutorResult());

    Queue::assertNotPushed(ProcessQueuedMessage::class);
});

// ─── Respond mode parentId routing ─────────────────────────────────

it('uses last user message as parentId in respond mode', function () {
    $middleware = app(PersistConversation::class);

    $conversation = Conversation::create(['agent' => 'test-agent']);

    $userMsg = Message::create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::User,
        'content' => 'User said this',
        'sequence' => 0,
        'is_active' => true,
    ]);

    $agent = makeTestAgent();
    $agent->forConversation($conversation->id);
    $agent->respond();

    $context = new AgentContext(
        request: makePersistConversationRequest(),
        agent: $agent,
        messages: [],
        meta: [],
    );

    $middleware->handle($context, fn () => makeFakeExecutorResultWithSteps());

    $assistantMsg = Message::where('conversation_id', $conversation->id)
        ->where('role', MessageRole::Assistant)
        ->first();

    expect($assistantMsg->parent_id)->toBe($userMsg->id);
});

// ─── History injection into request ─────────────────────────────────

it('prepends conversation history into the request messages', function () {
    $middleware = app(PersistConversation::class);

    $conversation = Conversation::create(['agent' => 'test-agent']);

    // Seed two prior messages
    Message::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'content' => 'Prior question',
        'sequence' => 0,
    ]);

    Message::factory()->fromAssistant('test-agent')->create([
        'conversation_id' => $conversation->id,
        'content' => 'Prior answer',
        'sequence' => 1,
    ]);

    $agent = makeTestAgent();
    $agent->forConversation($conversation->id);

    $capturedRequest = null;

    $context = new AgentContext(
        request: makePersistConversationRequest(),
        agent: $agent,
        messages: [],
        meta: [],
    );

    $middleware->handle($context, function (AgentContext $ctx) use (&$capturedRequest) {
        $capturedRequest = $ctx->request;

        return makeFakeExecutorResult();
    });

    // History (2 seeded messages) should be in the request
    expect($capturedRequest)->not->toBeNull();
    expect($capturedRequest->messages)->toHaveCount(2);

    // Both a user and assistant message should be present
    $types = array_map(fn ($m) => get_class($m), $capturedRequest->messages);
    expect($types)->toContain(UserMessage::class);
    expect($types)->toContain(AssistantMessage::class);
});

it('grows request messages as conversation history accumulates', function () {
    $middleware = app(PersistConversation::class);
    $conversations = app(ConversationService::class);

    $conversation = Conversation::create(['agent' => 'test-agent']);

    // Pre-store user message (simulates AgentRequest eager storage)
    $conversations->addMessage($conversation, new UserMessage(content: 'Hello'));

    // Turn 1 — respond to the pre-stored user message
    $agent = makeTestAgent();
    $agent->forConversation($conversation->id);
    $agent->respond();

    $capturedRequest1 = null;

    $context1 = new AgentContext(
        request: makePersistConversationRequest(),
        agent: $agent,
        messages: [],
        meta: [],
    );

    $middleware->handle($context1, function (AgentContext $ctx) use (&$capturedRequest1) {
        $capturedRequest1 = $ctx->request;

        return makeFakeExecutorResult();
    });

    // First turn: should have 1 message (the pre-stored user message)
    expect($capturedRequest1->messages)->toHaveCount(1);

    // Pre-store another user message for turn 2
    $conversations->addMessage($conversation, new UserMessage(content: 'Follow up'));

    // Turn 2 — should see turn 1's user + assistant + turn 2's user
    $agent2 = makeTestAgent();
    $agent2->forConversation($conversation->id);
    $agent2->respond();

    $capturedRequest2 = null;

    $context2 = new AgentContext(
        request: makePersistConversationRequest(),
        agent: $agent2,
        messages: [],
        meta: [],
    );

    $middleware->handle($context2, function (AgentContext $ctx) use (&$capturedRequest2) {
        $capturedRequest2 = $ctx->request;

        return makeFakeExecutorResult();
    });

    // Second turn: should have 3 messages (user1 + assistant1 + user2)
    expect($capturedRequest2->messages)->toHaveCount(3);
});

it('prepends history before existing request messages without duplication', function () {
    $middleware = app(PersistConversation::class);

    $conversation = Conversation::create(['agent' => 'test-agent']);

    Message::factory()->fromUser()->create([
        'conversation_id' => $conversation->id,
        'content' => 'Prior question',
        'sequence' => 0,
    ]);

    Message::factory()->fromAssistant('test-agent')->create([
        'conversation_id' => $conversation->id,
        'content' => 'Prior answer',
        'sequence' => 1,
    ]);

    $agent = makeTestAgent();
    $agent->forConversation($conversation->id);

    $requestWithMessages = new TextRequest(
        model: 'gpt-5',
        instructions: null,
        message: 'New message',
        messageMedia: [],
        messages: [new UserMessage(content: 'Manual context')],
        maxTokens: null,
        temperature: null,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );

    $capturedRequest = null;

    $context = new AgentContext(
        request: $requestWithMessages,
        agent: $agent,
        messages: [new UserMessage(content: 'Manual context')],
        meta: [],
    );

    $middleware->handle($context, function (AgentContext $ctx) use (&$capturedRequest) {
        $capturedRequest = $ctx->request;

        return makeFakeExecutorResult();
    });

    // History (2) + existing manual message (1) = 3
    expect($capturedRequest->messages)->toHaveCount(3);

    // History (2) prepended before manual context (1)
    // Manual context should be last
    expect($capturedRequest->messages[2]->content)->toBe('Manual context');
});

it('respects message limit when loading history', function () {
    $middleware = app(PersistConversation::class);

    $conversation = Conversation::create(['agent' => 'test-agent']);

    // Seed 5 messages
    for ($i = 0; $i < 5; $i++) {
        $factory = $i % 2 === 0
            ? Message::factory()->fromUser()
            : Message::factory()->fromAssistant('test-agent');

        $factory->create([
            'conversation_id' => $conversation->id,
            'content' => "Message {$i}",
            'sequence' => $i,
        ]);
    }

    // Agent with message limit of 2
    $agent = makeTestAgent();
    $agent->forConversation($conversation->id);
    $agent->withMessageLimit(2);

    $capturedRequest = null;

    $context = new AgentContext(
        request: makePersistConversationRequest(),
        agent: $agent,
        messages: [],
        meta: [],
    );

    $middleware->handle($context, function (AgentContext $ctx) use (&$capturedRequest) {
        $capturedRequest = $ctx->request;

        return makeFakeExecutorResult();
    });

    // Only the 2 most recent messages should be loaded (not all 5)
    expect($capturedRequest->messages)->toHaveCount(2);

    // Should NOT have all 5 — limit of 2 was applied
    expect($capturedRequest->messages)->not->toHaveCount(5);
});

// ─── Event dispatch ────────────────────────────────────────────────

it('dispatches ConversationMessageStored event after storing assistant message', function () {
    Event::fake([ConversationMessageStored::class]);

    $middleware = app(PersistConversation::class);

    $conversation = Conversation::create(['agent' => 'test-agent']);

    $agent = makeTestAgent();
    $agent->forConversation($conversation->id);

    $context = new AgentContext(
        request: makePersistConversationRequest(),
        agent: $agent,
        messages: [new UserMessage(content: 'Hello')],
        meta: [],
    );

    $middleware->handle($context, fn () => makeFakeExecutorResult());

    Event::assertDispatched(ConversationMessageStored::class, function ($event) use ($conversation) {
        return $event->conversationId === $conversation->id
            && $event->role === Role::Assistant
            && $event->agent === 'test-agent'
            && $event->messageId !== null;
    });
});

// ─── Tool asset attachment ─────────────────────────────────────────

it('attaches tool-created assets to stored assistant message', function () {
    Storage::fake('local');
    config()->set('atlas.storage.disk', 'local');

    $middleware = app(PersistConversation::class);

    $conversation = Conversation::create(['agent' => 'test-agent']);

    $agent = makeTestAgent();
    $agent->forConversation($conversation->id);

    // Create an execution with a tool-created asset
    $executionService = app(ExecutionService::class);
    $executionService->createExecution(
        provider: 'openai',
        model: 'gpt-4o',
        type: ExecutionType::Text,
    );
    $executionService->beginExecution();

    $execution = $executionService->getExecution();

    // Create an asset linked to this execution with tool_execution source
    $asset = Asset::create([
        'type' => AssetType::Image,
        'mime_type' => 'image/png',
        'filename' => 'test.png',
        'path' => 'atlas/assets/test.png',
        'disk' => 'local',
        'size_bytes' => 100,
        'content_hash' => hash('sha256', 'fake'),
        'execution_id' => $execution->id,
        'metadata' => ['source' => 'tool_execution', 'tool_call_id' => 'call_abc', 'tool_name' => 'generate_image'],
    ]);

    $context = new AgentContext(
        request: makePersistConversationRequest(),
        agent: $agent,
        messages: [new UserMessage(content: 'Generate an image')],
        meta: ['execution_id' => $execution->id],
    );

    $middleware->handle($context, fn () => makeFakeExecutorResult());

    // Verify asset is attached to the stored assistant message
    $assistantMsg = Message::where('conversation_id', $conversation->id)
        ->where('role', MessageRole::Assistant)
        ->first();

    $attachment = MessageAttachment::where('message_id', $assistantMsg->id)
        ->where('asset_id', $asset->id)
        ->first();

    expect($attachment)->not->toBeNull();
    expect($attachment->metadata['tool_call_id'])->toBe('call_abc');
    expect($attachment->metadata['tool_name'])->toBe('generate_image');
});

// ─── Non-ExecutorResult passthrough ────────────────────────────────

it('returns TextResponse unchanged without persistence', function () {
    $middleware = app(PersistConversation::class);

    $conversation = Conversation::create(['agent' => 'test-agent']);

    $agent = makeTestAgent();
    $agent->forConversation($conversation->id);

    $textResponse = new TextResponse(
        text: 'Simple response',
        usage: new Usage(10, 5),
        finishReason: FinishReason::Stop,
    );

    $context = new AgentContext(
        request: makePersistConversationRequest(),
        agent: $agent,
        messages: [new UserMessage(content: 'Hello')],
        meta: [],
    );

    $result = $middleware->handle($context, fn () => $textResponse);

    // TextResponse should pass through without any persistence
    expect($result)->toBe($textResponse);

    // No assistant messages should be stored
    $assistantMessages = Message::where('conversation_id', $conversation->id)
        ->where('role', MessageRole::Assistant)
        ->count();

    expect($assistantMessages)->toBe(0);
});

// ─── Consumer metadata ─────────────────────────────────────────────

it('stores consumer metadata on conversation when metadata is null', function () {
    $middleware = app(PersistConversation::class);

    $conversation = Conversation::create([
        'agent' => 'test-agent',
        'metadata' => null,
    ]);

    $agent = makeTestAgent();
    $agent->forConversation($conversation->id);

    $request = new TextRequest(
        model: 'gpt-5',
        instructions: null,
        message: 'Hello',
        messageMedia: [],
        messages: [],
        maxTokens: null,
        temperature: null,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
        meta: ['user_id' => 42, 'session' => 'abc'],
    );

    $context = new AgentContext(
        request: $request,
        agent: $agent,
        messages: [new UserMessage(content: 'Hello')],
        meta: [],
    );

    $middleware->handle($context, fn () => makeFakeExecutorResult());

    $conversation->refresh();
    expect($conversation->metadata)->toBe(['user_id' => 42, 'session' => 'abc']);
});

it('does not overwrite existing conversation metadata', function () {
    $middleware = app(PersistConversation::class);

    $conversation = Conversation::create([
        'agent' => 'test-agent',
        'metadata' => ['existing' => 'data'],
    ]);

    $agent = makeTestAgent();
    $agent->forConversation($conversation->id);

    $request = new TextRequest(
        model: 'gpt-5',
        instructions: null,
        message: 'Hello',
        messageMedia: [],
        messages: [],
        maxTokens: null,
        temperature: null,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
        meta: ['new_key' => 'new_value'],
    );

    $context = new AgentContext(
        request: $request,
        agent: $agent,
        messages: [new UserMessage(content: 'Hello')],
        meta: [],
    );

    $middleware->handle($context, fn () => makeFakeExecutorResult());

    $conversation->refresh();
    // Existing metadata should NOT be overwritten
    expect($conversation->metadata)->toBe(['existing' => 'data']);
});
