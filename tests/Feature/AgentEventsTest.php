<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Contracts\AgentExecutorContract;
use Atlasphp\Atlas\Agents\Events\AgentExecuted;
use Atlasphp\Atlas\Agents\Events\AgentExecuting;
use Atlasphp\Atlas\Agents\Events\AgentFailed;
use Atlasphp\Atlas\Agents\Events\AgentStreamed;
use Atlasphp\Atlas\Agents\Events\AgentStreaming;
use Atlasphp\Atlas\Agents\Support\AgentContext;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Illuminate\Support\Facades\Event;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\ValueObjects\Usage;

test('execute dispatches AgentExecuting and AgentExecuted events', function () {
    Event::fake([AgentExecuting::class, AgentExecuted::class]);

    Prism::fake([
        TextResponseFake::make()
            ->withText('Hello')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = app(AgentExecutorContract::class);
    $agent = new TestAgent;
    $context = new AgentContext;

    $executor->execute($agent, 'Hello', $context);

    Event::assertDispatched(AgentExecuting::class, function (AgentExecuting $event) {
        return $event->agent->key() === 'test-agent' && $event->input === 'Hello';
    });

    Event::assertDispatched(AgentExecuted::class, function (AgentExecuted $event) {
        return $event->agent->key() === 'test-agent' && $event->response->text() === 'Hello';
    });
});

test('execute dispatches AgentFailed on exception', function () {
    Event::fake([AgentFailed::class]);

    // Use Prism fake that will throw when accessed
    Prism::fake([
        TextResponseFake::make()
            ->withText('Hello')
            ->withUsage(new Usage(10, 5)),
    ]);

    // Register a before_execute pipeline handler that throws
    $registry = app(\Atlasphp\Atlas\Pipelines\PipelineRegistry::class);
    $registry->define('agent.before_execute');
    $registry->register('agent.before_execute', new class implements \Atlasphp\Atlas\Contracts\PipelineContract
    {
        public function handle(mixed $data, \Closure $next): mixed
        {
            throw new \RuntimeException('Forced failure for testing');
        }
    });

    $executor = app(AgentExecutorContract::class);
    $agent = new TestAgent;
    $context = new AgentContext;

    try {
        $executor->execute($agent, 'Hello', $context);
    } catch (\Throwable) {
        // Expected
    }

    Event::assertDispatched(AgentFailed::class, function (AgentFailed $event) {
        return $event->agent->key() === 'test-agent';
    });
});

test('stream dispatches AgentStreaming event', function () {
    Event::fake([AgentStreaming::class, AgentStreamed::class]);

    Prism::fake([
        TextResponseFake::make()
            ->withText('Streamed text')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = app(AgentExecutorContract::class);
    $agent = new TestAgent;
    $context = new AgentContext;

    $streamResponse = $executor->stream($agent, 'Hello', $context);

    Event::assertDispatched(AgentStreaming::class, function (AgentStreaming $event) {
        return $event->agent->key() === 'test-agent';
    });

    // Consume the stream to trigger AgentStreamed
    foreach ($streamResponse as $event) {
        // consume
    }

    Event::assertDispatched(AgentStreamed::class, function (AgentStreamed $event) {
        return $event->agent->key() === 'test-agent' && count($event->events) > 0;
    });
});

test('events are not dispatched when events.enabled is false', function () {
    Event::fake([AgentExecuting::class, AgentExecuted::class]);

    config(['atlas.events.enabled' => false]);

    Prism::fake([
        TextResponseFake::make()
            ->withText('Hello')
            ->withUsage(new Usage(10, 5)),
    ]);

    $executor = app(AgentExecutorContract::class);
    $agent = new TestAgent;
    $context = new AgentContext;

    $executor->execute($agent, 'Hello', $context);

    Event::assertNotDispatched(AgentExecuting::class);
    Event::assertNotDispatched(AgentExecuted::class);
});
