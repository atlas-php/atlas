<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Testing\FakeAgentExecutor;
use Atlasphp\Atlas\Testing\Support\FakeResponseSequence;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Illuminate\Support\Collection;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\StreamStartEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Text\Response as PrismResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

function createTestPrismResponse(string $text): PrismResponse
{
    return new PrismResponse(
        steps: new Collection,
        text: $text,
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(10, 20),
        meta: new Meta('req-123', 'gpt-4'),
        messages: new Collection,
    );
}

beforeEach(function () {
    $this->executor = new FakeAgentExecutor;
    $this->agent = new TestAgent;
    $this->context = new ExecutionContext;
});

// === addSequence ===

test('addSequence registers response sequence for agent', function () {
    $response = createTestPrismResponse('Hello');
    $sequence = (new FakeResponseSequence)->push($response);

    $this->executor->addSequence('test-agent', $sequence);

    $result = $this->executor->execute($this->agent, 'Hi', $this->context);

    expect($result->text)->toBe('Hello');
});

test('addSequence returns self for chaining', function () {
    $sequence = new FakeResponseSequence;

    $result = $this->executor->addSequence('test-agent', $sequence);

    expect($result)->toBe($this->executor);
});

// === setDefaultSequence ===

test('setDefaultSequence sets fallback for unmatched agents', function () {
    $response = createTestPrismResponse('Default response');
    $sequence = (new FakeResponseSequence)->push($response);

    $this->executor->setDefaultSequence($sequence);

    $result = $this->executor->execute($this->agent, 'Hi', $this->context);

    expect($result->text)->toBe('Default response');
});

test('agent-specific sequence takes priority over default', function () {
    $defaultResponse = createTestPrismResponse('Default');
    $specificResponse = createTestPrismResponse('Specific');

    $this->executor->setDefaultSequence((new FakeResponseSequence)->push($defaultResponse));
    $this->executor->addSequence('test-agent', (new FakeResponseSequence)->push($specificResponse));

    $result = $this->executor->execute($this->agent, 'Hi', $this->context);

    expect($result->text)->toBe('Specific');
});

// === preventStrayRequests ===

test('preventStrayRequests throws for unconfigured agents', function () {
    $this->executor->preventStrayRequests(true);

    $this->executor->execute($this->agent, 'Hi', $this->context);
})->throws(RuntimeException::class, "Unexpected agent execution: 'test-agent'");

test('preventStrayRequests can be disabled', function () {
    $this->executor->preventStrayRequests(true);
    $this->executor->preventStrayRequests(false);

    $result = $this->executor->execute($this->agent, 'Hi', $this->context);

    expect($result)->toBeInstanceOf(PrismResponse::class);
});

// === execute ===

test('execute returns configured response', function () {
    $response = createTestPrismResponse('Test response');
    $this->executor->addSequence('test-agent', (new FakeResponseSequence)->push($response));

    $result = $this->executor->execute($this->agent, 'Hello', $this->context);

    expect($result->text)->toBe('Test response');
});

test('execute returns empty response when no configuration', function () {
    $result = $this->executor->execute($this->agent, 'Hello', $this->context);

    expect($result)->toBeInstanceOf(PrismResponse::class);
    expect($result->text)->toBe('');
});

test('execute throws configured exception', function () {
    $exception = new RuntimeException('Test error');
    $this->executor->addSequence('test-agent', (new FakeResponseSequence)->push($exception));

    $this->executor->execute($this->agent, 'Hello', $this->context);
})->throws(RuntimeException::class, 'Test error');

test('execute records request even when throwing', function () {
    $exception = new RuntimeException('Test error');
    $this->executor->addSequence('test-agent', (new FakeResponseSequence)->push($exception));

    try {
        $this->executor->execute($this->agent, 'Hello', $this->context);
    } catch (RuntimeException) {
        // Expected
    }

    expect($this->executor->recorded())->toHaveCount(1);
});

// === stream ===

test('stream returns generator with stream events', function () {
    $response = createTestPrismResponse('Hello World');
    $this->executor->addSequence('test-agent', (new FakeResponseSequence)->push($response));

    $events = iterator_to_array($this->executor->stream($this->agent, 'Hi', $this->context));

    // Should have start, text deltas, and end events
    expect($events[0])->toBeInstanceOf(StreamStartEvent::class);
    expect($events[count($events) - 1])->toBeInstanceOf(StreamEndEvent::class);

    // Find text delta events
    $textDeltas = array_filter($events, fn ($e) => $e instanceof TextDeltaEvent);
    expect($textDeltas)->not->toBeEmpty();
});

test('stream text deltas combine to full text', function () {
    $response = createTestPrismResponse('Hello World');
    $this->executor->addSequence('test-agent', (new FakeResponseSequence)->push($response));

    $events = iterator_to_array($this->executor->stream($this->agent, 'Hi', $this->context));

    $text = '';
    foreach ($events as $event) {
        if ($event instanceof TextDeltaEvent) {
            $text .= $event->delta;
        }
    }

    expect($text)->toBe('Hello World');
});

test('stream throws configured exception', function () {
    $exception = new RuntimeException('Stream error');
    $this->executor->addSequence('test-agent', (new FakeResponseSequence)->push($exception));

    iterator_to_array($this->executor->stream($this->agent, 'Hi', $this->context));
})->throws(RuntimeException::class, 'Stream error');

test('stream records request', function () {
    $response = createTestPrismResponse('Hello');
    $this->executor->addSequence('test-agent', (new FakeResponseSequence)->push($response));

    iterator_to_array($this->executor->stream($this->agent, 'Hi', $this->context));

    expect($this->executor->recorded())->toHaveCount(1);
});

// === recorded ===

test('recorded returns all recorded requests', function () {
    $response = createTestPrismResponse('Response');
    $this->executor->addSequence('test-agent', (new FakeResponseSequence)->push($response)->push($response));

    $this->executor->execute($this->agent, 'First', $this->context);
    $this->executor->execute($this->agent, 'Second', $this->context);

    $recorded = $this->executor->recorded();

    expect($recorded)->toHaveCount(2);
    expect($recorded[0]->input)->toBe('First');
    expect($recorded[1]->input)->toBe('Second');
});

test('recorded returns empty array initially', function () {
    expect($this->executor->recorded())->toBe([]);
});

// === recordedFor ===

test('recordedFor filters by agent key', function () {
    $response = createTestPrismResponse('Response');
    $this->executor->setDefaultSequence((new FakeResponseSequence)->push($response)->push($response));

    // Create two different agents
    $agent1 = new TestAgent;
    $agent2 = new class extends \Atlasphp\Atlas\Agents\AgentDefinition
    {
        public function key(): string
        {
            return 'other-agent';
        }

        public function provider(): string
        {
            return 'openai';
        }

        public function model(): string
        {
            return 'gpt-4';
        }
    };

    $this->executor->execute($agent1, 'For agent 1', $this->context);
    $this->executor->execute($agent2, 'For agent 2', $this->context);

    $forAgent1 = $this->executor->recordedFor('test-agent');
    $forAgent2 = $this->executor->recordedFor('other-agent');

    expect($forAgent1)->toHaveCount(1);
    expect($forAgent2)->toHaveCount(1);
    expect(array_values($forAgent1)[0]->input)->toBe('For agent 1');
});

// === reset ===

test('reset clears recorded requests', function () {
    $response = createTestPrismResponse('Response');
    $this->executor->addSequence('test-agent', (new FakeResponseSequence)->push($response));

    $this->executor->execute($this->agent, 'Hello', $this->context);
    expect($this->executor->recorded())->toHaveCount(1);

    $this->executor->reset();
    expect($this->executor->recorded())->toBe([]);
});

test('reset resets response sequences', function () {
    $response1 = createTestPrismResponse('First');
    $response2 = createTestPrismResponse('Second');
    $sequence = (new FakeResponseSequence)->push($response1)->push($response2);
    $this->executor->addSequence('test-agent', $sequence);

    // Use first response
    $this->executor->execute($this->agent, 'Hello', $this->context);

    // Reset
    $this->executor->reset();

    // Should get first response again
    $result = $this->executor->execute($this->agent, 'Hello', $this->context);
    expect($result->text)->toBe('First');
});

test('reset returns self for chaining', function () {
    $result = $this->executor->reset();

    expect($result)->toBe($this->executor);
});

// === Sequence behavior ===

test('sequence returns responses in order', function () {
    $response1 = createTestPrismResponse('First');
    $response2 = createTestPrismResponse('Second');
    $response3 = createTestPrismResponse('Third');

    $sequence = (new FakeResponseSequence)
        ->push($response1)
        ->push($response2)
        ->push($response3);

    $this->executor->addSequence('test-agent', $sequence);

    expect($this->executor->execute($this->agent, 'Hi', $this->context)->text)->toBe('First');
    expect($this->executor->execute($this->agent, 'Hi', $this->context)->text)->toBe('Second');
    expect($this->executor->execute($this->agent, 'Hi', $this->context)->text)->toBe('Third');
});
