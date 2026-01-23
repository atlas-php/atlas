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
