<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Agent;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Executor\ExecutorResult;
use Atlasphp\Atlas\Executor\Step;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Middleware\AgentContext;
use Atlasphp\Atlas\Persistence\Concerns\HasConversations;
use Atlasphp\Atlas\Persistence\Enums\MessageRole;
use Atlasphp\Atlas\Persistence\Middleware\PersistConversation;
use Atlasphp\Atlas\Persistence\Models\Conversation;
use Atlasphp\Atlas\Persistence\Models\Message;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\Usage;

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

    expect($assistantMessages)->toHaveCount(2)
        ->and($assistantMessages[0]->content)->toBe('Step one response')
        ->and($assistantMessages[0]->agent)->toBe('test-agent')
        ->and($assistantMessages[1]->content)->toBe('Step two response')
        ->and($assistantMessages[1]->agent)->toBe('test-agent');
});
