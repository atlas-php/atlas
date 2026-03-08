<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Events\AgentExecuted;
use Atlasphp\Atlas\Agents\Events\AgentExecuting;
use Atlasphp\Atlas\Agents\Events\AgentFailed;
use Atlasphp\Atlas\Agents\Events\AgentStreamed;
use Atlasphp\Atlas\Agents\Events\AgentStreaming;
use Atlasphp\Atlas\Agents\Support\AgentContext;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Testing\Support\FakeResponseSequence;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;

test('AgentExecuting holds agent, input, and context', function () {
    $agent = new TestAgent;
    $context = new AgentContext;
    $event = new AgentExecuting($agent, 'Hello', $context);

    expect($event->agent)->toBe($agent);
    expect($event->input)->toBe('Hello');
    expect($event->context)->toBe($context);
});

test('AgentExecuted holds agent, input, context, and response', function () {
    $agent = new TestAgent;
    $context = new AgentContext;
    $response = new AgentResponse(
        response: FakeResponseSequence::emptyResponse(),
        agent: $agent,
        input: 'Hello',
        systemPrompt: null,
        context: $context,
    );
    $event = new AgentExecuted($agent, 'Hello', $context, $response);

    expect($event->agent)->toBe($agent);
    expect($event->input)->toBe('Hello');
    expect($event->context)->toBe($context);
    expect($event->response)->toBe($response);
});

test('AgentStreaming holds agent, input, and context', function () {
    $agent = new TestAgent;
    $context = new AgentContext;
    $event = new AgentStreaming($agent, 'Hello', $context);

    expect($event->agent)->toBe($agent);
    expect($event->input)->toBe('Hello');
    expect($event->context)->toBe($context);
});

test('AgentStreamed holds agent, input, context, and events', function () {
    $agent = new TestAgent;
    $context = new AgentContext;
    $streamEvents = [
        new TextDeltaEvent(id: 'evt_1', timestamp: time(), delta: 'Hi', messageId: 'msg_1'),
    ];
    $event = new AgentStreamed($agent, 'Hello', $context, $streamEvents);

    expect($event->agent)->toBe($agent);
    expect($event->input)->toBe('Hello');
    expect($event->context)->toBe($context);
    expect($event->events)->toBe($streamEvents);
});

test('AgentFailed holds agent, input, context, and exception', function () {
    $agent = new TestAgent;
    $context = new AgentContext;
    $exception = new \RuntimeException('Something broke');
    $event = new AgentFailed($agent, 'Hello', $context, $exception);

    expect($event->agent)->toBe($agent);
    expect($event->input)->toBe('Hello');
    expect($event->context)->toBe($context);
    expect($event->exception)->toBe($exception);
});
