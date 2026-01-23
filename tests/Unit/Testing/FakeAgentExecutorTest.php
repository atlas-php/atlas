<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Streaming\StreamResponse;
use Atlasphp\Atlas\Testing\FakeAgentExecutor;
use Atlasphp\Atlas\Testing\Support\FakeResponseSequence;

beforeEach(function () {
    $this->executor = new FakeAgentExecutor;
    $this->agent = Mockery::mock(AgentContract::class);
    $this->agent->shouldReceive('key')->andReturn('test-agent');
});

test('it records execute requests', function () {
    $response = AgentResponse::text('Hello');
    $sequence = (new FakeResponseSequence)->push($response);
    $this->executor->addSequence('test-agent', $sequence);

    $this->executor->execute($this->agent, 'Hello');

    $recorded = $this->executor->recorded();
    expect($recorded)->toHaveCount(1);
    expect($recorded[0]->input)->toBe('Hello');
    expect($recorded[0]->agentKey())->toBe('test-agent');
});

test('it returns configured response', function () {
    $response = AgentResponse::text('Configured response');
    $sequence = (new FakeResponseSequence)->push($response);
    $this->executor->addSequence('test-agent', $sequence);

    $result = $this->executor->execute($this->agent, 'Input');

    expect($result->text)->toBe('Configured response');
});

test('it uses default sequence for unmatched agents', function () {
    $response = AgentResponse::text('Default');
    $sequence = (new FakeResponseSequence)->push($response);
    $this->executor->setDefaultSequence($sequence);

    $result = $this->executor->execute($this->agent, 'Input');

    expect($result->text)->toBe('Default');
});

test('it throws on stray request when prevention enabled', function () {
    $this->executor->preventStrayRequests();

    expect(fn () => $this->executor->execute($this->agent, 'Input'))
        ->toThrow(RuntimeException::class, 'Unexpected agent execution');
});

test('it returns empty response when stray allowed', function () {
    $result = $this->executor->execute($this->agent, 'Input');

    expect($result)->toBeInstanceOf(AgentResponse::class);
    expect($result->text)->toBeNull();
});

test('it records stream requests', function () {
    $response = AgentResponse::text('Hello');
    $sequence = (new FakeResponseSequence)->push($response);
    $this->executor->addSequence('test-agent', $sequence);

    $this->executor->stream($this->agent, 'Hello');

    $recorded = $this->executor->recorded();
    expect($recorded)->toHaveCount(1);
});

test('it returns stream response for streaming', function () {
    $response = AgentResponse::text('Streamed');
    $sequence = (new FakeResponseSequence)->push($response);
    $this->executor->addSequence('test-agent', $sequence);

    $result = $this->executor->stream($this->agent, 'Input');

    expect($result)->toBeInstanceOf(StreamResponse::class);
});

test('it throws configured exception', function () {
    $exception = new RuntimeException('Test error');
    $sequence = (new FakeResponseSequence)->push($exception);
    $this->executor->addSequence('test-agent', $sequence);

    expect(fn () => $this->executor->execute($this->agent, 'Input'))
        ->toThrow(RuntimeException::class, 'Test error');
});

test('it filters recorded requests by agent', function () {
    $response = AgentResponse::text('Hello');
    $sequence = (new FakeResponseSequence)->push($response);
    $this->executor->addSequence('test-agent', $sequence);

    $this->executor->execute($this->agent, 'Input');

    $recorded = $this->executor->recordedFor('test-agent');
    expect($recorded)->toHaveCount(1);

    $otherRecorded = $this->executor->recordedFor('other-agent');
    expect($otherRecorded)->toBeEmpty();
});

test('it resets recorded requests', function () {
    $response = AgentResponse::text('Hello');
    $sequence = (new FakeResponseSequence)->push($response);
    $this->executor->addSequence('test-agent', $sequence);

    $this->executor->execute($this->agent, 'Input');
    expect($this->executor->recorded())->toHaveCount(1);

    $this->executor->reset();
    expect($this->executor->recorded())->toBeEmpty();
});

test('it throws when realExecutor is set but no response configured', function () {
    $mockRealExecutor = Mockery::mock(\Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract::class);
    $this->executor->setRealExecutor($mockRealExecutor);

    expect(fn () => $this->executor->execute($this->agent, 'Input'))
        ->toThrow(RuntimeException::class, 'Cannot fall back to real executor');
});

test('setRealExecutor returns self for chaining', function () {
    $mockRealExecutor = Mockery::mock(\Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract::class);
    $result = $this->executor->setRealExecutor($mockRealExecutor);

    expect($result)->toBe($this->executor);
});

test('it handles StreamResponse in execute by returning empty AgentResponse', function () {
    $streamResponse = \Atlasphp\Atlas\Testing\Support\StreamEventFactory::fromText('Streamed');
    $sequence = (new FakeResponseSequence)->push($streamResponse);
    $this->executor->addSequence('test-agent', $sequence);

    $result = $this->executor->execute($this->agent, 'Input');

    expect($result)->toBeInstanceOf(AgentResponse::class);
    expect($result->text)->toBeNull();
});

test('it throws configured exception in stream', function () {
    $exception = new RuntimeException('Stream error');
    $sequence = (new FakeResponseSequence)->push($exception);
    $this->executor->addSequence('test-agent', $sequence);

    expect(fn () => $this->executor->stream($this->agent, 'Input'))
        ->toThrow(RuntimeException::class, 'Stream error');
});

test('it records request before throwing exception in execute', function () {
    $exception = new RuntimeException('Test error');
    $sequence = (new FakeResponseSequence)->push($exception);
    $this->executor->addSequence('test-agent', $sequence);

    try {
        $this->executor->execute($this->agent, 'Input');
    } catch (RuntimeException $e) {
        // Expected
    }

    expect($this->executor->recorded())->toHaveCount(1);
});

test('it records request before throwing exception in stream', function () {
    $exception = new RuntimeException('Test error');
    $sequence = (new FakeResponseSequence)->push($exception);
    $this->executor->addSequence('test-agent', $sequence);

    try {
        $this->executor->stream($this->agent, 'Input');
    } catch (RuntimeException $e) {
        // Expected
    }

    expect($this->executor->recorded())->toHaveCount(1);
});

test('preventStrayRequests returns self for chaining', function () {
    $result = $this->executor->preventStrayRequests();

    expect($result)->toBe($this->executor);
});

test('preventStrayRequests can be disabled', function () {
    $this->executor->preventStrayRequests(true);
    $this->executor->preventStrayRequests(false);

    $result = $this->executor->execute($this->agent, 'Input');

    expect($result)->toBeInstanceOf(AgentResponse::class);
});

test('addSequence returns self for chaining', function () {
    $sequence = new FakeResponseSequence;
    $result = $this->executor->addSequence('test-agent', $sequence);

    expect($result)->toBe($this->executor);
});

test('setDefaultSequence returns self for chaining', function () {
    $sequence = new FakeResponseSequence;
    $result = $this->executor->setDefaultSequence($sequence);

    expect($result)->toBe($this->executor);
});

test('it converts AgentResponse to StreamResponse in stream method', function () {
    $response = AgentResponse::text('Hello streaming');
    $sequence = (new FakeResponseSequence)->push($response);
    $this->executor->addSequence('test-agent', $sequence);

    $result = $this->executor->stream($this->agent, 'Input');

    expect($result)->toBeInstanceOf(StreamResponse::class);
});
