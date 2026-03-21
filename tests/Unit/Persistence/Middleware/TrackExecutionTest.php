<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Executor\ExecutorResult;
use Atlasphp\Atlas\Middleware\AgentContext;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Middleware\TrackExecution;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\Usage;

function makeExecutionTextRequest(): TextRequest
{
    return new TextRequest(
        model: 'gpt-5',
        instructions: null,
        message: 'Hello',
        messageMedia: [],
        messages: [],
        maxTokens: null,
        temperature: null,
        schema: null,
        tools: [],
        providerTools: [],
        providerOptions: [],
    );
}

function makeExecutionFakeResult(): ExecutorResult
{
    return new ExecutorResult(
        text: 'test',
        reasoning: null,
        steps: [],
        usage: new Usage(10, 5),
        finishReason: FinishReason::Stop,
        meta: [],
    );
}

it('creates execution and completes on success', function () {
    $middleware = app(TrackExecution::class);

    $context = new AgentContext(
        request: makeExecutionTextRequest(),
        meta: [],
    );

    $result = $middleware->handle($context, fn () => makeExecutionFakeResult());

    $execution = Execution::latest('id')->first();

    expect($execution)->not->toBeNull();
    expect($execution->status)->toBe(ExecutionStatus::Completed);
    expect($execution->started_at)->not->toBeNull();
    expect($execution->completed_at)->not->toBeNull();
});

it('sets execution_id in context meta', function () {
    $middleware = app(TrackExecution::class);

    $context = new AgentContext(
        request: makeExecutionTextRequest(),
        meta: [],
    );

    $middleware->handle($context, fn () => makeExecutionFakeResult());

    expect($context->meta['execution_id'])->toBeInt();

    $execution = Execution::find($context->meta['execution_id']);
    expect($execution)->not->toBeNull();
});

it('defaults execution type to Text when not in meta', function () {
    $middleware = app(TrackExecution::class);

    $context = new AgentContext(
        request: makeExecutionTextRequest(),
        meta: [],
    );

    $middleware->handle($context, fn () => makeExecutionFakeResult());

    $execution = Execution::latest('id')->first();
    expect($execution->type)->toBe(ExecutionType::Text);
});

it('reads execution type from meta', function () {
    $middleware = app(TrackExecution::class);

    $context = new AgentContext(
        request: makeExecutionTextRequest(),
        meta: ['_execution_type' => 'image'],
    );

    $middleware->handle($context, fn () => makeExecutionFakeResult());

    $execution = Execution::latest('id')->first();
    expect($execution->type)->toBe(ExecutionType::Image);
});

it('marks execution failed on exception', function () {
    $middleware = app(TrackExecution::class);

    $context = new AgentContext(
        request: makeExecutionTextRequest(),
        meta: [],
    );

    try {
        $middleware->handle($context, function () {
            throw new RuntimeException('Provider error');
        });
    } catch (RuntimeException) {
        // expected
    }

    $execution = Execution::latest('id')->first();
    expect($execution->status)->toBe(ExecutionStatus::Failed);
    expect($execution->error)->toContain('Provider error');
});

it('re-throws exception after marking failed', function () {
    $middleware = app(TrackExecution::class);

    $context = new AgentContext(
        request: makeExecutionTextRequest(),
        meta: [],
    );

    $middleware->handle($context, function () {
        throw new RuntimeException('Provider error');
    });
})->throws(RuntimeException::class, 'Provider error');

it('handles null agent gracefully', function () {
    $middleware = app(TrackExecution::class);

    $context = new AgentContext(
        request: makeExecutionTextRequest(),
        agent: null,
        meta: [],
    );

    $middleware->handle($context, fn () => makeExecutionFakeResult());

    $execution = Execution::latest('id')->first();

    expect($execution)->not->toBeNull();
    expect($execution->agent)->toBeNull();
    expect($execution->status)->toBe(ExecutionStatus::Completed);
});

it('falls back to config defaults when agent provider and model are null', function () {
    config()->set('atlas.defaults.text.provider', 'config-provider');
    config()->set('atlas.defaults.text.model', 'config-model');

    $middleware = app(TrackExecution::class);

    $agent = new class extends Agent
    {
        public function key(): string
        {
            return 'null-provider-agent';
        }
    };

    $context = new AgentContext(
        request: makeExecutionTextRequest(),
        agent: $agent,
        meta: [],
    );

    $result = $middleware->handle($context, fn () => makeExecutionFakeResult());

    $execution = Execution::latest('id')->first();

    expect($execution->provider)->toBe('config-provider')
        ->and($execution->model)->toBe('config-model');
});

it('attaches executionId to result', function () {
    $middleware = app(TrackExecution::class);

    $context = new AgentContext(
        request: makeExecutionTextRequest(),
        meta: [],
    );

    $result = $middleware->handle($context, fn () => makeExecutionFakeResult());

    expect($result->executionId)->toBeInt();

    $execution = Execution::find($result->executionId);
    expect($execution)->not->toBeNull();
    expect($execution->status)->toBe(ExecutionStatus::Completed);
});
