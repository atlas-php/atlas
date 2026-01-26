<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Support\AgentContext;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Testing\AtlasFake;
use Atlasphp\Atlas\Testing\FakeAgentExecutor;
use Atlasphp\Atlas\Testing\PendingFakeRequest;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Text\Response as PrismResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

function createFakeTestResponse(string $text): PrismResponse
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
    $this->container = new Container;
    $this->fake = new AtlasFake($this->container);
    $this->agent = new TestAgent;
    $this->context = new AgentContext;
});

// === response ===

test('response with PrismResponse registers immediately', function () {
    $response = createFakeTestResponse('Hello');

    $result = $this->fake->response('test-agent', $response);

    expect($result)->toBe($this->fake);

    // Verify response is registered
    $this->fake->activate();
    $executor = $this->container->make(AgentExecutorContract::class);
    $prismResponse = $executor->execute($this->agent, 'Hi', $this->context);

    expect($prismResponse->text)->toBe('Hello');
});

test('response without PrismResponse returns PendingFakeRequest', function () {
    $result = $this->fake->response('test-agent');

    expect($result)->toBeInstanceOf(PendingFakeRequest::class);
});

// === sequence ===

test('sequence sets default responses for any agent', function () {
    $response1 = createFakeTestResponse('First');
    $response2 = createFakeTestResponse('Second');

    $this->fake->sequence([$response1, $response2]);
    $this->fake->activate();

    $executor = $this->container->make(AgentExecutorContract::class);

    expect($executor->execute($this->agent, 'Hi', $this->context)->text)->toBe('First');
    expect($executor->execute($this->agent, 'Hi', $this->context)->text)->toBe('Second');
});

test('sequence returns self for chaining', function () {
    $result = $this->fake->sequence([]);

    expect($result)->toBe($this->fake);
});

// === preventStrayRequests ===

test('preventStrayRequests enables prevention', function () {
    $this->fake->preventStrayRequests();
    $this->fake->activate();

    $executor = $this->container->make(AgentExecutorContract::class);

    $executor->execute($this->agent, 'Hi', $this->context);
})->throws(RuntimeException::class);

test('preventStrayRequests returns self for chaining', function () {
    $result = $this->fake->preventStrayRequests();

    expect($result)->toBe($this->fake);
});

// === allowStrayRequests ===

test('allowStrayRequests disables prevention', function () {
    $this->fake->preventStrayRequests();
    $this->fake->allowStrayRequests();
    $this->fake->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $result = $executor->execute($this->agent, 'Hi', $this->context);

    expect($result)->toBeInstanceOf(AgentResponse::class);
});

test('allowStrayRequests returns self for chaining', function () {
    $result = $this->fake->allowStrayRequests();

    expect($result)->toBe($this->fake);
});

// === recorded ===

test('recorded returns all recorded requests', function () {
    $response = createFakeTestResponse('Response');
    $this->fake->sequence([$response, $response]);
    $this->fake->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $executor->execute($this->agent, 'First', $this->context);
    $executor->execute($this->agent, 'Second', $this->context);

    $recorded = $this->fake->recorded();

    expect($recorded)->toHaveCount(2);
});

test('recorded returns empty array when nothing called', function () {
    expect($this->fake->recorded())->toBe([]);
});

// === recordedFor ===

test('recordedFor filters by agent key', function () {
    $response = createFakeTestResponse('Response');
    $this->fake->sequence([$response, $response]);
    $this->fake->activate();

    $executor = $this->container->make(AgentExecutorContract::class);

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

    $executor->execute($agent1, 'For agent 1', $this->context);
    $executor->execute($agent2, 'For agent 2', $this->context);

    expect($this->fake->recordedFor('test-agent'))->toHaveCount(1);
    expect($this->fake->recordedFor('other-agent'))->toHaveCount(1);
    expect($this->fake->recordedFor('non-existent'))->toBe([]);
});

// === reset ===

test('reset clears recorded requests', function () {
    $response = createFakeTestResponse('Response');
    $this->fake->sequence([$response]);
    $this->fake->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $executor->execute($this->agent, 'Hi', $this->context);

    expect($this->fake->recorded())->toHaveCount(1);

    $this->fake->reset();

    expect($this->fake->recorded())->toBe([]);
});

test('reset returns self for chaining', function () {
    $result = $this->fake->reset();

    expect($result)->toBe($this->fake);
});

// === activate ===

test('activate swaps container binding', function () {
    $this->fake->activate();

    $executor = $this->container->make(AgentExecutorContract::class);

    expect($executor)->toBeInstanceOf(FakeAgentExecutor::class);
});

test('activate stores original executor', function () {
    // First bind a real executor
    $originalExecutor = Mockery::mock(AgentExecutorContract::class);
    $this->container->instance(AgentExecutorContract::class, $originalExecutor);

    $this->fake->activate();

    // Fake should be bound
    expect($this->container->make(AgentExecutorContract::class))->toBeInstanceOf(FakeAgentExecutor::class);
});

test('activate returns self for chaining', function () {
    $result = $this->fake->activate();

    expect($result)->toBe($this->fake);
});

// === restore ===

test('restore rebinds original executor', function () {
    // First bind a real executor
    $originalExecutor = Mockery::mock(AgentExecutorContract::class);
    $this->container->instance(AgentExecutorContract::class, $originalExecutor);

    $this->fake->activate();
    $this->fake->restore();

    expect($this->container->make(AgentExecutorContract::class))->toBe($originalExecutor);
});

test('restore does nothing when no original', function () {
    $this->fake->activate();
    $this->fake->restore();

    // Should not throw
    expect(true)->toBeTrue();
});

// === getExecutor ===

test('getExecutor returns FakeAgentExecutor', function () {
    $executor = $this->fake->getExecutor();

    expect($executor)->toBeInstanceOf(FakeAgentExecutor::class);
});

// === registerSequence ===

test('registerSequence adds sequence to executor', function () {
    $response = createFakeTestResponse('Registered');
    $sequence = (new \Atlasphp\Atlas\Testing\Support\FakeResponseSequence)->push($response);

    $this->fake->registerSequence('test-agent', $sequence);
    $this->fake->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $result = $executor->execute($this->agent, 'Hi', $this->context);

    expect($result->text)->toBe('Registered');
});

// === Integration ===

test('full workflow with response configuration', function () {
    $response = createFakeTestResponse('Configured response');

    $this->fake
        ->response('test-agent', $response)
        ->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $result = $executor->execute($this->agent, 'Hello', $this->context);

    expect($result->text)->toBe('Configured response');
    expect($this->fake->recorded())->toHaveCount(1);
});

test('full workflow with PendingFakeRequest', function () {
    $response = createFakeTestResponse('Fluent response');

    $this->fake
        ->response('test-agent')
        ->return($response);

    $this->fake->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $result = $executor->execute($this->agent, 'Hello', $this->context);

    expect($result->text)->toBe('Fluent response');
});

afterEach(function () {
    Mockery::close();
});
