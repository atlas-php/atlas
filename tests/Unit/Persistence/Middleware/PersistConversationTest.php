<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Executor\ExecutorResult;
use Atlasphp\Atlas\Executor\Step;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Middleware\AgentContext;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;
use Atlasphp\Atlas\Persistence\Enums\MessageRole;
use Atlasphp\Atlas\Persistence\Enums\MessageStatus;
use Atlasphp\Atlas\Persistence\Middleware\PersistConversation;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\Message;
use Atlasphp\Atlas\Persistence\ProcessQueuedMessage;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\Usage;
use Illuminate\Support\Facades\Queue;

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

it('stores user message after execution', function () {
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

    $middleware->handle($context, fn () => makeFakeExecutorResult());

    $userMessage = Message::where('conversation_id', $conversation->id)
        ->where('role', MessageRole::User)
        ->first();

    expect($userMessage)->not->toBeNull()
        ->and($userMessage->content)->toBe('Hello')
        ->and($userMessage->role)->toBe(MessageRole::User);
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
