<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Support\AgentContext;
use Atlasphp\Atlas\Testing\AtlasFake;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Text\Response as PrismResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

function createPendingFakeTestResponse(string $text): PrismResponse
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

// === return ===

test('return registers single response', function () {
    $response = createPendingFakeTestResponse('Single response');

    $this->fake
        ->response('test-agent')
        ->return($response);

    $this->fake->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $result = $executor->execute($this->agent, 'Hi', $this->context);

    expect($result->text)->toBe('Single response');
});

test('return returns AtlasFake for chaining', function () {
    $response = createPendingFakeTestResponse('Response');

    $result = $this->fake
        ->response('test-agent')
        ->return($response);

    expect($result)->toBe($this->fake);
});

// === returnSequence ===

test('returnSequence registers multiple responses', function () {
    $response1 = createPendingFakeTestResponse('First');
    $response2 = createPendingFakeTestResponse('Second');
    $response3 = createPendingFakeTestResponse('Third');

    $this->fake
        ->response('test-agent')
        ->returnSequence([$response1, $response2, $response3]);

    $this->fake->activate();

    $executor = $this->container->make(AgentExecutorContract::class);

    expect($executor->execute($this->agent, 'Hi', $this->context)->text)->toBe('First');
    expect($executor->execute($this->agent, 'Hi', $this->context)->text)->toBe('Second');
    expect($executor->execute($this->agent, 'Hi', $this->context)->text)->toBe('Third');
});

test('returnSequence returns AtlasFake for chaining', function () {
    $result = $this->fake
        ->response('test-agent')
        ->returnSequence([]);

    expect($result)->toBe($this->fake);
});

// === throw ===

test('throw registers exception', function () {
    $exception = new RuntimeException('Test error');

    $this->fake
        ->response('test-agent')
        ->throw($exception);

    $this->fake->activate();

    $executor = $this->container->make(AgentExecutorContract::class);
    $executor->execute($this->agent, 'Hi', $this->context);
})->throws(RuntimeException::class, 'Test error');

test('throw returns AtlasFake for chaining', function () {
    $exception = new RuntimeException('Error');

    $result = $this->fake
        ->response('test-agent')
        ->throw($exception);

    expect($result)->toBe($this->fake);
});

// === whenEmpty ===

test('whenEmpty sets fallback response', function () {
    $response = createPendingFakeTestResponse('Initial');
    $fallback = createPendingFakeTestResponse('Fallback');

    $this->fake
        ->response('test-agent')
        ->whenEmpty($fallback)
        ->return($response);

    $this->fake->activate();

    $executor = $this->container->make(AgentExecutorContract::class);

    // First call gets initial response
    expect($executor->execute($this->agent, 'Hi', $this->context)->text)->toBe('Initial');

    // Subsequent calls get fallback
    expect($executor->execute($this->agent, 'Hi', $this->context)->text)->toBe('Fallback');
    expect($executor->execute($this->agent, 'Hi', $this->context)->text)->toBe('Fallback');
});

test('whenEmpty sets fallback exception', function () {
    $response = createPendingFakeTestResponse('Initial');
    $exception = new RuntimeException('Exhausted');

    $this->fake
        ->response('test-agent')
        ->whenEmpty($exception)
        ->return($response);

    $this->fake->activate();

    $executor = $this->container->make(AgentExecutorContract::class);

    // First call succeeds
    $executor->execute($this->agent, 'Hi', $this->context);

    // Second call throws
    $executor->execute($this->agent, 'Hi', $this->context);
})->throws(RuntimeException::class, 'Exhausted');

test('whenEmpty returns self for chaining', function () {
    $fallback = createPendingFakeTestResponse('Fallback');

    $pending = $this->fake->response('test-agent');
    $result = $pending->whenEmpty($fallback);

    expect($result)->toBe($pending);
});

// === Chaining ===

test('fluent API allows full configuration', function () {
    $response1 = createPendingFakeTestResponse('First');
    $response2 = createPendingFakeTestResponse('Second');
    $fallback = createPendingFakeTestResponse('Fallback');

    $this->fake
        ->response('test-agent')
        ->whenEmpty($fallback)
        ->returnSequence([$response1, $response2]);

    $this->fake->activate();

    $executor = $this->container->make(AgentExecutorContract::class);

    expect($executor->execute($this->agent, 'Hi', $this->context)->text)->toBe('First');
    expect($executor->execute($this->agent, 'Hi', $this->context)->text)->toBe('Second');
    expect($executor->execute($this->agent, 'Hi', $this->context)->text)->toBe('Fallback');
});

test('multiple agents can be configured', function () {
    $response1 = createPendingFakeTestResponse('Agent 1 response');
    $response2 = createPendingFakeTestResponse('Agent 2 response');

    $this->fake
        ->response('test-agent')
        ->return($response1);

    $this->fake
        ->response('other-agent')
        ->return($response2);

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

    expect($executor->execute($agent1, 'Hi', $this->context)->text)->toBe('Agent 1 response');
    expect($executor->execute($agent2, 'Hi', $this->context)->text)->toBe('Agent 2 response');
});
