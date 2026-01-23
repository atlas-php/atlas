<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Agents\Support\ExecutionContext;
use Atlasphp\Atlas\Testing\Support\RecordedRequest;
use Prism\Prism\Schema\ObjectSchema;

test('it stores request data', function () {
    $agent = Mockery::mock(AgentContract::class);
    $agent->shouldReceive('key')->andReturn('test-agent');
    $response = AgentResponse::text('Response');

    $request = new RecordedRequest(
        agent: $agent,
        input: 'Hello',
        context: null,
        schema: null,
        response: $response,
        timestamp: 1234567890,
    );

    expect($request->agent)->toBe($agent);
    expect($request->input)->toBe('Hello');
    expect($request->response)->toBe($response);
    expect($request->timestamp)->toBe(1234567890);
});

test('it returns agent key', function () {
    $agent = Mockery::mock(AgentContract::class);
    $agent->shouldReceive('key')->andReturn('my-agent');

    $request = new RecordedRequest(
        agent: $agent,
        input: 'Test',
        context: null,
        schema: null,
        response: AgentResponse::empty(),
        timestamp: time(),
    );

    expect($request->agentKey())->toBe('my-agent');
});

test('it checks if input contains string', function () {
    $agent = Mockery::mock(AgentContract::class);
    $agent->shouldReceive('key')->andReturn('test');

    $request = new RecordedRequest(
        agent: $agent,
        input: 'Hello World, how are you?',
        context: null,
        schema: null,
        response: AgentResponse::empty(),
        timestamp: time(),
    );

    expect($request->inputContains('World'))->toBeTrue();
    expect($request->inputContains('Goodbye'))->toBeFalse();
});

test('it checks for metadata presence', function () {
    $agent = Mockery::mock(AgentContract::class);
    $agent->shouldReceive('key')->andReturn('test');

    $context = new ExecutionContext(
        metadata: ['user_id' => 123, 'role' => 'admin']
    );

    $request = new RecordedRequest(
        agent: $agent,
        input: 'Test',
        context: $context,
        schema: null,
        response: AgentResponse::empty(),
        timestamp: time(),
    );

    expect($request->hasMetadata('user_id'))->toBeTrue();
    expect($request->hasMetadata('user_id', 123))->toBeTrue();
    expect($request->hasMetadata('user_id', 456))->toBeFalse();
    expect($request->hasMetadata('nonexistent'))->toBeFalse();
});

test('it returns false for metadata when no context', function () {
    $agent = Mockery::mock(AgentContract::class);
    $agent->shouldReceive('key')->andReturn('test');

    $request = new RecordedRequest(
        agent: $agent,
        input: 'Test',
        context: null,
        schema: null,
        response: AgentResponse::empty(),
        timestamp: time(),
    );

    expect($request->hasMetadata('any'))->toBeFalse();
});

test('it checks for schema presence', function () {
    $agent = Mockery::mock(AgentContract::class);
    $agent->shouldReceive('key')->andReturn('test');

    $schema = new ObjectSchema('Test', 'A test schema', [], []);

    $requestWithSchema = new RecordedRequest(
        agent: $agent,
        input: 'Test',
        context: null,
        schema: $schema,
        response: AgentResponse::empty(),
        timestamp: time(),
    );

    $requestWithoutSchema = new RecordedRequest(
        agent: $agent,
        input: 'Test',
        context: null,
        schema: null,
        response: AgentResponse::empty(),
        timestamp: time(),
    );

    expect($requestWithSchema->hasSchema())->toBeTrue();
    expect($requestWithoutSchema->hasSchema())->toBeFalse();
});
