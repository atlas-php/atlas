<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Events\AgentStreamChunk;
use Atlasphp\Atlas\Agents\Support\AgentContext;
use Atlasphp\Atlas\Agents\Support\AgentStreamResponse;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Streaming\Events\ErrorEvent;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\StreamEvent;
use Prism\Prism\Streaming\Events\StreamStartEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\ValueObjects\Usage;

/**
 * @return Generator<int, StreamEvent>
 */
function createTestStreamGenerator(string $text = 'Hello World'): Generator
{
    yield new StreamStartEvent(
        id: 'evt_0',
        timestamp: time(),
        model: 'test-model',
        provider: 'test',
    );

    $chunks = str_split($text, 5);
    foreach ($chunks as $i => $chunk) {
        yield new TextDeltaEvent(
            id: 'evt_'.($i + 1),
            timestamp: time(),
            delta: $chunk,
            messageId: 'msg_123',
        );
    }

    yield new StreamEndEvent(
        id: 'evt_final',
        timestamp: time(),
        finishReason: FinishReason::Stop,
        usage: new Usage(10, 20),
    );
}

// === Constructor and Properties ===

test('AgentStreamResponse stores all constructor parameters', function () {
    $stream = createTestStreamGenerator();
    $agent = new TestAgent;
    $context = new AgentContext(
        variables: ['name' => 'John'],
        metadata: ['session_id' => 'abc'],
    );

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: $agent,
        input: 'Hello world',
        systemPrompt: 'You are helpful.',
        context: $context,
    );

    expect($response->agent)->toBe($agent);
    expect($response->input)->toBe('Hello world');
    expect($response->systemPrompt)->toBe('You are helpful.');
    expect($response->context)->toBe($context);
});

test('AgentStreamResponse accepts null systemPrompt', function () {
    $stream = createTestStreamGenerator();
    $agent = new TestAgent;
    $context = new AgentContext;

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: $agent,
        input: 'Hello',
        systemPrompt: null,
        context: $context,
    );

    expect($response->systemPrompt)->toBeNull();
});

// === Agent Accessors (Available Before Iteration) ===

test('agentKey() returns the agent key before iteration', function () {
    $stream = createTestStreamGenerator();
    $agent = new TestAgent;

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: $agent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response->agentKey())->toBe($agent->key());
});

test('agentName() returns the agent name before iteration', function () {
    $stream = createTestStreamGenerator();
    $agent = new TestAgent;

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: $agent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response->agentName())->toBe($agent->name());
});

test('agentDescription() returns the agent description before iteration', function () {
    $stream = createTestStreamGenerator();
    $agent = new TestAgent;

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: $agent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response->agentDescription())->toBe($agent->description());
});

test('metadata() returns context metadata before iteration', function () {
    $stream = createTestStreamGenerator();
    $context = new AgentContext(
        metadata: ['user_id' => 123, 'trace_id' => 'xyz'],
    );

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: $context,
    );

    expect($response->metadata())->toBe(['user_id' => 123, 'trace_id' => 'xyz']);
});

test('variables() returns context variables before iteration', function () {
    $stream = createTestStreamGenerator();
    $context = new AgentContext(
        variables: ['name' => 'Jane', 'role' => 'admin'],
    );

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: $context,
    );

    expect($response->variables())->toBe(['name' => 'Jane', 'role' => 'admin']);
});

// === IteratorAggregate Implementation ===

test('AgentStreamResponse implements IteratorAggregate', function () {
    $stream = createTestStreamGenerator();

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response)->toBeInstanceOf(IteratorAggregate::class);
});

test('foreach iteration works seamlessly', function () {
    $stream = createTestStreamGenerator('Hi');

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $events = [];
    foreach ($response as $event) {
        $events[] = $event;
    }

    expect($events)->toHaveCount(3); // start, delta, end
    expect($events[0])->toBeInstanceOf(StreamStartEvent::class);
    expect($events[1])->toBeInstanceOf(TextDeltaEvent::class);
    expect($events[2])->toBeInstanceOf(StreamEndEvent::class);
});

test('iterator yields correct TextDeltaEvent content', function () {
    $stream = createTestStreamGenerator('Hello');

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: new TestAgent,
        input: 'Hi',
        systemPrompt: null,
        context: new AgentContext,
    );

    $textContent = '';
    foreach ($response as $event) {
        if ($event instanceof TextDeltaEvent) {
            $textContent .= $event->delta;
        }
    }

    expect($textContent)->toBe('Hello');
});

// === Event Collection ===

test('events() returns empty array before consumption', function () {
    $stream = createTestStreamGenerator();

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response->events())->toBe([]);
});

test('events() collects events during iteration', function () {
    $stream = createTestStreamGenerator('ABC');

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    // Consume stream
    iterator_to_array($response);

    $events = $response->events();
    expect($events)->toHaveCount(3); // start, delta, end
    expect($events[0])->toBeInstanceOf(StreamStartEvent::class);
});

test('events() returns all collected events after full consumption', function () {
    $stream = createTestStreamGenerator('Hello World Test');

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: new TestAgent,
        input: 'Test input',
        systemPrompt: null,
        context: new AgentContext,
    );

    // Consume fully
    foreach ($response as $event) {
        // Just iterate
    }

    $events = $response->events();

    // Should have: start + multiple deltas + end
    expect(count($events))->toBeGreaterThan(2);
    expect($events[0])->toBeInstanceOf(StreamStartEvent::class);
    expect(end($events))->toBeInstanceOf(StreamEndEvent::class);
});

// === Consumption State ===

test('isConsumed() returns false before iteration', function () {
    $stream = createTestStreamGenerator();

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response->isConsumed())->toBeFalse();
});

test('isConsumed() returns true after full iteration', function () {
    $stream = createTestStreamGenerator();

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    // Consume fully
    iterator_to_array($response);

    expect($response->isConsumed())->toBeTrue();
});

test('isConsumed() returns true after foreach completes', function () {
    $stream = createTestStreamGenerator('Test');

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    foreach ($response as $event) {
        // Just iterate
    }

    expect($response->isConsumed())->toBeTrue();
});

// === each() Callback ===

test('each() fires callback on every event during iteration', function () {
    $stream = createTestStreamGenerator('Hi');
    $receivedEvents = [];

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $response->each(function (StreamEvent $event) use (&$receivedEvents) {
        $receivedEvents[] = $event;
    });

    iterator_to_array($response);

    expect($receivedEvents)->toHaveCount(3); // start, delta, end
});

test('each() receives the response as second argument', function () {
    $stream = createTestStreamGenerator('Hi');
    $receivedResponse = null;

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $response->each(function (StreamEvent $event, AgentStreamResponse $r) use (&$receivedResponse) {
        $receivedResponse = $r;
    });

    iterator_to_array($response);

    expect($receivedResponse)->toBe($response);
});

test('each() does NOT fire on replay', function () {
    $stream = createTestStreamGenerator('Hi');
    $callCount = 0;

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $response->each(function () use (&$callCount) {
        $callCount++;
    });

    // First iteration
    iterator_to_array($response);
    $firstCount = $callCount;

    // Replay
    iterator_to_array($response);

    expect($callCount)->toBe($firstCount);
});

// === Stream Replay ===

test('replay yields same events after consumption', function () {
    $stream = createTestStreamGenerator('Hi');

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $firstEvents = iterator_to_array($response);
    $replayEvents = iterator_to_array($response);

    expect($replayEvents)->toHaveCount(count($firstEvents));
    expect($replayEvents[0])->toBeInstanceOf(StreamStartEvent::class);
    expect($replayEvents[1])->toBeInstanceOf(TextDeltaEvent::class);
    expect($replayEvents[2])->toBeInstanceOf(StreamEndEvent::class);
});

test('then() does NOT fire on replay', function () {
    $stream = createTestStreamGenerator('Hi');
    $thenCount = 0;

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $response->then(function () use (&$thenCount) {
        $thenCount++;
    });

    // First iteration
    iterator_to_array($response);
    expect($thenCount)->toBe(1);

    // Replay
    iterator_to_array($response);
    expect($thenCount)->toBe(1);
});

// === onError() ===

test('onError() fires callback for ErrorEvent', function () {
    $generator = (function (): Generator {
        yield new StreamStartEvent('evt_0', time(), 'test-model', 'test');
        yield new ErrorEvent('evt_1', time(), 'rate_limit', 'Too many requests', true);
        yield new StreamEndEvent('evt_final', time(), FinishReason::Stop, new Usage(0, 0));
    })();

    $receivedError = null;

    $response = new AgentStreamResponse(
        stream: $generator,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $response->onError(function (ErrorEvent $error) use (&$receivedError) {
        $receivedError = $error;
    });

    iterator_to_array($response);

    expect($receivedError)->toBeInstanceOf(ErrorEvent::class);
    expect($receivedError->errorType)->toBe('rate_limit');
    expect($receivedError->message)->toBe('Too many requests');
    expect($receivedError->recoverable)->toBeTrue();
});

test('onError() does not fire for non-error events', function () {
    $stream = createTestStreamGenerator('Hi');
    $errorCalled = false;

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $response->onError(function () use (&$errorCalled) {
        $errorCalled = true;
    });

    iterator_to_array($response);

    expect($errorCalled)->toBeFalse();
});

test('onError() does NOT fire on replay', function () {
    $generator = (function (): Generator {
        yield new ErrorEvent('evt_1', time(), 'rate_limit', 'Error', true);
    })();

    $errorCount = 0;

    $response = new AgentStreamResponse(
        stream: $generator,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $response->onError(function () use (&$errorCount) {
        $errorCount++;
    });

    iterator_to_array($response);
    $firstCount = $errorCount;

    iterator_to_array($response);
    expect($errorCount)->toBe($firstCount);
});

// === broadcast() / broadcastNow() ===

test('broadcast() sets request ID and returns self', function () {
    $stream = createTestStreamGenerator('Hi');

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $result = $response->broadcast('req-123');

    expect($result)->toBe($response);
    expect($response->broadcastRequestId())->toBe('req-123');
});

test('broadcast() auto-generates request ID when null', function () {
    $stream = createTestStreamGenerator('Hi');

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $response->broadcast();

    expect($response->broadcastRequestId())->not->toBeNull();
    expect(strlen($response->broadcastRequestId()))->toBe(32);
});

test('broadcastRequestId() returns null when broadcasting not enabled', function () {
    $stream = createTestStreamGenerator('Hi');

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response->broadcastRequestId())->toBeNull();
});

test('broadcast() dispatches AgentStreamChunk events during iteration', function () {
    Event::fake();

    $stream = createTestStreamGenerator('Hi');

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $response->broadcast('req-123');
    iterator_to_array($response);

    Event::assertDispatched(AgentStreamChunk::class, 3); // start, delta, end
    Event::assertDispatched(AgentStreamChunk::class, function (AgentStreamChunk $chunk) {
        return $chunk->requestId === 'req-123';
    });
});

test('broadcastNow() dispatches BroadcastEvent synchronously via Bus', function () {
    Bus::fake();

    $stream = createTestStreamGenerator('Hi');

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $response->broadcastNow('req-456');
    iterator_to_array($response);

    Bus::assertDispatchedSync(\Illuminate\Broadcasting\BroadcastEvent::class, 3);
});

// === Combined Usage ===

test('agent context accessible before, during, and after iteration', function () {
    $stream = createTestStreamGenerator('Data');
    $agent = new TestAgent;
    $context = new AgentContext(
        metadata: ['request_id' => '999'],
        variables: ['name' => 'Test'],
    );

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: $agent,
        input: 'Process',
        systemPrompt: 'Be helpful',
        context: $context,
    );

    // Before iteration
    expect($response->agentKey())->toBe($agent->key());
    expect($response->metadata())->toBe(['request_id' => '999']);
    expect($response->isConsumed())->toBeFalse();

    // During iteration
    $eventCount = 0;
    foreach ($response as $event) {
        $eventCount++;
        // Can still access agent info during iteration
        expect($response->agentKey())->toBe($agent->key());
    }

    // After iteration
    expect($response->agentKey())->toBe($agent->key());
    expect($response->metadata())->toBe(['request_id' => '999']);
    expect($response->isConsumed())->toBeTrue();
    expect($response->events())->toHaveCount($eventCount);
});

test('metadata and variables remain consistent', function () {
    $stream = createTestStreamGenerator();
    $context = new AgentContext(
        metadata: ['key1' => 'value1'],
        variables: ['var1' => 'val1'],
    );

    $response = new AgentStreamResponse(
        stream: $stream,
        agent: new TestAgent,
        input: 'Test',
        systemPrompt: null,
        context: $context,
    );

    $metadataBefore = $response->metadata();
    $variablesBefore = $response->variables();

    iterator_to_array($response);

    expect($response->metadata())->toBe($metadataBefore);
    expect($response->variables())->toBe($variablesBefore);
});
