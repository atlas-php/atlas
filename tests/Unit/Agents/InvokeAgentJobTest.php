<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Jobs\InvokeAgent;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Agents\Support\AgentContext;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Prism\Prism\Testing\TextResponseFake;

test('InvokeAgent stores agent key, input, and serialized context', function () {
    $context = new AgentContext(
        variables: ['user' => 'Alice'],
        metadata: ['trace_id' => '123'],
    );

    $job = new InvokeAgent(
        agentKey: 'test-agent',
        input: 'Hello',
        serializedContext: $context->toArray(),
    );

    expect($job->agentKey)->toBe('test-agent');
    expect($job->input)->toBe('Hello');
    expect($job->serializedContext['variables'])->toBe(['user' => 'Alice']);
    expect($job->serializedContext['metadata'])->toBe(['trace_id' => '123']);
});

test('InvokeAgent then() sets success callback', function () {
    $job = new InvokeAgent('test-agent', 'Hello', []);

    $result = $job->then(fn ($response) => null);

    expect($result)->toBe($job);
    expect($job->thenCallback)->not->toBeNull();
});

test('InvokeAgent catch() sets failure callback', function () {
    $job = new InvokeAgent('test-agent', 'Hello', []);

    $result = $job->catch(fn ($e) => null);

    expect($result)->toBe($job);
    expect($job->catchCallback)->not->toBeNull();
});

test('handle() resolves agent and executes via executor', function () {
    $agent = Mockery::mock(AgentContract::class);
    $prismResponse = TextResponseFake::make()->withText('Hello back!');
    $agentResponse = new AgentResponse(
        response: $prismResponse,
        agent: $agent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $resolver = Mockery::mock(AgentResolver::class);
    $resolver->shouldReceive('resolve')
        ->once()
        ->with('test-agent')
        ->andReturn($agent);

    $executor = Mockery::mock(AgentExecutorContract::class);
    $executor->shouldReceive('execute')
        ->once()
        ->withArgs(function ($a, $input, $ctx) use ($agent) {
            return $a === $agent
                && $input === 'Hello'
                && $ctx instanceof AgentContext
                && $ctx->variables === ['user' => 'Alice'];
        })
        ->andReturn($agentResponse);

    $context = new AgentContext(variables: ['user' => 'Alice']);
    $job = new InvokeAgent('test-agent', 'Hello', $context->toArray());

    $job->handle($resolver, $executor);
});

test('handle() invokes then callback on success', function () {
    $agent = Mockery::mock(AgentContract::class);
    $prismResponse = TextResponseFake::make()->withText('Done!');
    $agentResponse = new AgentResponse(
        response: $prismResponse,
        agent: $agent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $resolver = Mockery::mock(AgentResolver::class);
    $resolver->shouldReceive('resolve')->andReturn($agent);

    $executor = Mockery::mock(AgentExecutorContract::class);
    $executor->shouldReceive('execute')->andReturn($agentResponse);

    $job = new InvokeAgent('test-agent', 'Hello', []);

    $callbackResponse = null;
    $job->then(function (AgentResponse $r) use (&$callbackResponse) {
        $callbackResponse = $r;
    });

    $job->handle($resolver, $executor);

    expect($callbackResponse)->toBe($agentResponse);
    expect($callbackResponse->text())->toBe('Done!');
});

test('handle() invokes catch callback on failure and does not rethrow', function () {
    $agent = Mockery::mock(AgentContract::class);

    $resolver = Mockery::mock(AgentResolver::class);
    $resolver->shouldReceive('resolve')->andReturn($agent);

    $exception = new RuntimeException('Agent execution failed');
    $executor = Mockery::mock(AgentExecutorContract::class);
    $executor->shouldReceive('execute')->andThrow($exception);

    $job = new InvokeAgent('test-agent', 'Hello', []);

    $caughtException = null;
    $job->catch(function (Throwable $e) use (&$caughtException) {
        $caughtException = $e;
    });

    $job->handle($resolver, $executor);

    expect($caughtException)->toBe($exception);
    expect($caughtException->getMessage())->toBe('Agent execution failed');
});

test('handle() rethrows exception when no catch callback is set', function () {
    $agent = Mockery::mock(AgentContract::class);

    $resolver = Mockery::mock(AgentResolver::class);
    $resolver->shouldReceive('resolve')->andReturn($agent);

    $executor = Mockery::mock(AgentExecutorContract::class);
    $executor->shouldReceive('execute')->andThrow(new RuntimeException('Boom'));

    $job = new InvokeAgent('test-agent', 'Hello', []);

    $job->handle($resolver, $executor);
})->throws(RuntimeException::class, 'Boom');

test('handle() does not invoke then callback when not set', function () {
    $agent = Mockery::mock(AgentContract::class);
    $prismResponse = TextResponseFake::make()->withText('OK');
    $agentResponse = new AgentResponse(
        response: $prismResponse,
        agent: $agent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    $resolver = Mockery::mock(AgentResolver::class);
    $resolver->shouldReceive('resolve')->andReturn($agent);

    $executor = Mockery::mock(AgentExecutorContract::class);
    $executor->shouldReceive('execute')->andReturn($agentResponse);

    $job = new InvokeAgent('test-agent', 'Hello', []);

    // Should not throw — just completes without callback
    $job->handle($resolver, $executor);

    expect($job->thenCallback)->toBeNull();
});

test('handle() restores context from serialized data', function () {
    $agent = Mockery::mock(AgentContract::class);
    $prismResponse = TextResponseFake::make()->withText('OK');

    $resolver = Mockery::mock(AgentResolver::class);
    $resolver->shouldReceive('resolve')->andReturn($agent);

    $capturedContext = null;
    $executor = Mockery::mock(AgentExecutorContract::class);
    $executor->shouldReceive('execute')
        ->withArgs(function ($a, $input, $ctx) use (&$capturedContext) {
            $capturedContext = $ctx;

            return true;
        })
        ->andReturn(new AgentResponse(
            response: $prismResponse,
            agent: $agent,
            input: 'Hello',
            systemPrompt: null,
            context: new AgentContext,
        ));

    $originalContext = new AgentContext(
        variables: ['user' => 'Bob'],
        metadata: ['trace' => 'abc'],
        providerOverride: 'anthropic',
        modelOverride: 'claude-4',
    );

    $job = new InvokeAgent('test-agent', 'Hello', $originalContext->toArray());
    $job->handle($resolver, $executor);

    expect($capturedContext)->toBeInstanceOf(AgentContext::class);
    expect($capturedContext->variables)->toBe(['user' => 'Bob']);
    expect($capturedContext->metadata)->toBe(['trace' => 'abc']);
    expect($capturedContext->providerOverride)->toBe('anthropic');
    expect($capturedContext->modelOverride)->toBe('claude-4');
});
