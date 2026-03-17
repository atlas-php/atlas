<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Events\AgentStreamChunk;
use Atlasphp\Atlas\Agents\Jobs\BroadcastAgent;
use Atlasphp\Atlas\Agents\Services\AgentResolver;
use Atlasphp\Atlas\Agents\Support\AgentContext;
use Atlasphp\Atlas\Agents\Support\AgentStreamResponse;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Illuminate\Support\Facades\Event;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Streaming\Events\ErrorEvent;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\StreamEvent;
use Prism\Prism\Streaming\Events\StreamStartEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Streaming\Events\ThinkingEvent;
use Prism\Prism\ValueObjects\Usage;

test('BroadcastAgent stores agent key, input, context, and request ID', function () {
    $context = new AgentContext(
        variables: ['user' => 'Alice'],
    );

    $job = new BroadcastAgent(
        agentKey: 'test-agent',
        input: 'Hello',
        serializedContext: $context->toArray(),
        requestId: 'req-123',
    );

    expect($job->agentKey)->toBe('test-agent');
    expect($job->input)->toBe('Hello');
    expect($job->serializedContext['variables'])->toBe(['user' => 'Alice']);
    expect($job->requestId)->toBe('req-123');
});

test('handle() resolves agent and streams via executor', function () {
    Event::fake();

    $agent = new TestAgent;
    $context = new AgentContext;

    $streamEvents = createStreamEvents();
    $streamResponse = createStreamResponse($streamEvents, $agent, $context);

    $resolver = Mockery::mock(AgentResolver::class);
    $resolver->shouldReceive('resolve')
        ->once()
        ->with('test-agent')
        ->andReturn($agent);

    $executor = Mockery::mock(AgentExecutorContract::class);
    $executor->shouldReceive('stream')
        ->once()
        ->withArgs(function ($a, $input, $ctx) use ($agent) {
            return $a === $agent && $input === 'Hello' && $ctx instanceof AgentContext;
        })
        ->andReturn($streamResponse);

    $job = new BroadcastAgent('test-agent', 'Hello', $context->toArray(), 'req-abc');
    $job->handle($resolver, $executor);

    Event::assertDispatched(AgentStreamChunk::class);
});

test('handle() broadcasts stream_start chunk with eventKey for StreamStartEvent', function () {
    Event::fake();

    $agent = new TestAgent;
    $context = new AgentContext;

    $streamEvents = [
        new StreamStartEvent('id-1', time(), 'gpt-4', 'openai'),
    ];
    $streamResponse = createStreamResponse($streamEvents, $agent, $context);

    $resolver = Mockery::mock(AgentResolver::class);
    $resolver->shouldReceive('resolve')->andReturn($agent);

    $executor = Mockery::mock(AgentExecutorContract::class);
    $executor->shouldReceive('stream')->andReturn($streamResponse);

    $job = new BroadcastAgent('test-agent', 'Hello', $context->toArray(), 'req-1');
    $job->handle($resolver, $executor);

    Event::assertDispatched(AgentStreamChunk::class, function (AgentStreamChunk $chunk) {
        return $chunk->type === 'stream_start'
            && $chunk->agentKey === 'test-agent'
            && $chunk->requestId === 'req-1'
            && $chunk->metadata['model'] === 'gpt-4'
            && $chunk->metadata['provider'] === 'openai';
    });
});

test('handle() broadcasts text_delta chunks with delta extracted', function () {
    Event::fake();

    $agent = new TestAgent;
    $context = new AgentContext;

    $streamEvents = [
        new TextDeltaEvent('id-1', time(), 'Hello', 'msg-1'),
        new TextDeltaEvent('id-2', time(), ' world', 'msg-1'),
    ];
    $streamResponse = createStreamResponse($streamEvents, $agent, $context);

    $resolver = Mockery::mock(AgentResolver::class);
    $resolver->shouldReceive('resolve')->andReturn($agent);

    $executor = Mockery::mock(AgentExecutorContract::class);
    $executor->shouldReceive('stream')->andReturn($streamResponse);

    $job = new BroadcastAgent('test-agent', 'Hello', $context->toArray(), 'req-2');
    $job->handle($resolver, $executor);

    Event::assertDispatched(AgentStreamChunk::class, function (AgentStreamChunk $chunk) {
        return $chunk->type === 'text_delta' && $chunk->delta === 'Hello';
    });
    Event::assertDispatched(AgentStreamChunk::class, function (AgentStreamChunk $chunk) {
        return $chunk->type === 'text_delta' && $chunk->delta === ' world';
    });
});

test('handle() broadcasts stream_end chunk with full toArray data', function () {
    Event::fake();

    $agent = new TestAgent;
    $context = new AgentContext;

    $streamEvents = [
        new StreamEndEvent(
            'id-1',
            time(),
            FinishReason::Stop,
            new Usage(promptTokens: 10, completionTokens: 20),
        ),
    ];
    $streamResponse = createStreamResponse($streamEvents, $agent, $context);

    $resolver = Mockery::mock(AgentResolver::class);
    $resolver->shouldReceive('resolve')->andReturn($agent);

    $executor = Mockery::mock(AgentExecutorContract::class);
    $executor->shouldReceive('stream')->andReturn($streamResponse);

    $job = new BroadcastAgent('test-agent', 'Hello', $context->toArray(), 'req-3');
    $job->handle($resolver, $executor);

    Event::assertDispatched(AgentStreamChunk::class, function (AgentStreamChunk $chunk) {
        return $chunk->type === 'stream_end'
            && $chunk->metadata['usage']['prompt_tokens'] === 10
            && $chunk->metadata['usage']['completion_tokens'] === 20;
    });
});

test('handle() broadcasts thinking_delta chunks with delta extracted', function () {
    Event::fake();

    $agent = new TestAgent;
    $context = new AgentContext;

    $streamEvents = [
        new ThinkingEvent('id-1', time(), 'Let me think...', 'reason_1'),
    ];
    $streamResponse = createStreamResponse($streamEvents, $agent, $context);

    $resolver = Mockery::mock(AgentResolver::class);
    $resolver->shouldReceive('resolve')->andReturn($agent);

    $executor = Mockery::mock(AgentExecutorContract::class);
    $executor->shouldReceive('stream')->andReturn($streamResponse);

    $job = new BroadcastAgent('test-agent', 'Hello', $context->toArray(), 'req-think');
    $job->handle($resolver, $executor);

    Event::assertDispatched(AgentStreamChunk::class, function (AgentStreamChunk $chunk) {
        return $chunk->type === 'thinking_delta'
            && $chunk->delta === 'Let me think...'
            && $chunk->metadata['reasoning_id'] === 'reason_1';
    });
});

test('handle() broadcasts error chunks for ErrorEvent', function () {
    Event::fake();

    $agent = new TestAgent;
    $context = new AgentContext;

    $streamEvents = [
        new ErrorEvent('id-1', time(), 'rate_limit', 'Too many requests', true),
    ];
    $streamResponse = createStreamResponse($streamEvents, $agent, $context);

    $resolver = Mockery::mock(AgentResolver::class);
    $resolver->shouldReceive('resolve')->andReturn($agent);

    $executor = Mockery::mock(AgentExecutorContract::class);
    $executor->shouldReceive('stream')->andReturn($streamResponse);

    $job = new BroadcastAgent('test-agent', 'Hello', $context->toArray(), 'req-err');
    $job->handle($resolver, $executor);

    Event::assertDispatched(AgentStreamChunk::class, function (AgentStreamChunk $chunk) {
        return $chunk->type === 'error'
            && $chunk->delta === null
            && $chunk->metadata['error_type'] === 'rate_limit';
    });
});

test('handle() broadcasts correct number of chunks for full stream', function () {
    Event::fake();

    $agent = new TestAgent;
    $context = new AgentContext;

    $streamEvents = createStreamEvents();
    $streamResponse = createStreamResponse($streamEvents, $agent, $context);

    $resolver = Mockery::mock(AgentResolver::class);
    $resolver->shouldReceive('resolve')->andReturn($agent);

    $executor = Mockery::mock(AgentExecutorContract::class);
    $executor->shouldReceive('stream')->andReturn($streamResponse);

    $job = new BroadcastAgent('test-agent', 'Hello', $context->toArray(), 'req-4');
    $job->handle($resolver, $executor);

    // 1 start + 2 text deltas + 1 end = 4 chunks
    Event::assertDispatched(AgentStreamChunk::class, 4);
});

test('handle() does not broadcast events when events are disabled', function () {
    Event::fake();
    config()->set('atlas.events.enabled', false);

    $agent = new TestAgent;
    $context = new AgentContext;

    $streamEvents = createStreamEvents();
    $streamResponse = createStreamResponse($streamEvents, $agent, $context);

    $resolver = Mockery::mock(AgentResolver::class);
    $resolver->shouldReceive('resolve')->andReturn($agent);

    $executor = Mockery::mock(AgentExecutorContract::class);
    $executor->shouldReceive('stream')->andReturn($streamResponse);

    $job = new BroadcastAgent('test-agent', 'Hello', $context->toArray(), 'req-5');
    $job->handle($resolver, $executor);

    Event::assertNotDispatched(AgentStreamChunk::class);
});

test('handle() restores context from serialized data', function () {
    Event::fake();

    $agent = new TestAgent;
    $originalContext = new AgentContext(
        variables: ['user' => 'Bob'],
        metadata: ['trace' => 'xyz'],
    );

    $streamEvents = [new TextDeltaEvent('id-1', time(), 'Hi', 'msg-1')];
    $streamResponse = createStreamResponse($streamEvents, $agent, new AgentContext);

    $resolver = Mockery::mock(AgentResolver::class);
    $resolver->shouldReceive('resolve')->andReturn($agent);

    $capturedContext = null;
    $executor = Mockery::mock(AgentExecutorContract::class);
    $executor->shouldReceive('stream')
        ->withArgs(function ($a, $input, $ctx) use (&$capturedContext) {
            $capturedContext = $ctx;

            return true;
        })
        ->andReturn($streamResponse);

    $job = new BroadcastAgent('test-agent', 'Hello', $originalContext->toArray(), 'req-6');
    $job->handle($resolver, $executor);

    expect($capturedContext)->toBeInstanceOf(AgentContext::class);
    expect($capturedContext->variables)->toBe(['user' => 'Bob']);
    expect($capturedContext->metadata)->toBe(['trace' => 'xyz']);
});

// --- Helpers ---

/**
 * Create a set of stream events for a complete stream.
 *
 * @return array<int, StreamEvent>
 */
function createStreamEvents(): array
{
    $time = time();

    return [
        new StreamStartEvent('id-1', $time, 'gpt-4', 'openai'),
        new TextDeltaEvent('id-2', $time, 'Hello', 'msg-1'),
        new TextDeltaEvent('id-3', $time, ' world', 'msg-1'),
        new StreamEndEvent('id-4', $time, FinishReason::Stop, new Usage(promptTokens: 10, completionTokens: 5)),
    ];
}

/**
 * Create an AgentStreamResponse from an array of events.
 *
 * @param  array<int, StreamEvent>  $events
 */
function createStreamResponse(array $events, AgentContract $agent, AgentContext $context): AgentStreamResponse
{
    $generator = (function () use ($events): Generator {
        foreach ($events as $event) {
            yield $event;
        }
    })();

    return new AgentStreamResponse(
        stream: $generator,
        agent: $agent,
        input: 'Hello',
        systemPrompt: null,
        context: $context,
    );
}
