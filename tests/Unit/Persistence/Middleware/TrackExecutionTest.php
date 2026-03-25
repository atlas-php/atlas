<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agent;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Exceptions\MaxStepsExceededException;
use Atlasphp\Atlas\Executor\ExecutorResult;
use Atlasphp\Atlas\Middleware\AgentContext;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Middleware\TrackExecution;
use Atlasphp\Atlas\Persistence\Models\Conversation;
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
        meta: ['execution_type' => 'image'],
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

it('handles Provider enum returned from agent provider method', function () {
    $middleware = app(TrackExecution::class);

    $agent = new class extends Agent
    {
        public function key(): string
        {
            return 'enum-provider-agent';
        }

        public function provider(): Provider|string|null
        {
            return Provider::xAI;
        }

        public function model(): ?string
        {
            return 'grok-4';
        }
    };

    $context = new AgentContext(
        request: makeExecutionTextRequest(),
        agent: $agent,
        meta: [],
    );

    $result = $middleware->handle($context, fn () => makeExecutionFakeResult());

    $execution = Execution::latest('id')->first();

    expect($execution->provider)->toBe('xai')
        ->and($execution->model)->toBe('grok-4');
});

it('marks execution failed when MaxStepsExceededException is thrown', function () {
    $middleware = app(TrackExecution::class);

    $context = new AgentContext(
        request: makeExecutionTextRequest(),
        meta: [],
    );

    try {
        $middleware->handle($context, function () {
            throw new MaxStepsExceededException(limit: 2, current: 3);
        });
    } catch (MaxStepsExceededException) {
        // expected
    }

    $execution = Execution::latest('id')->first();

    expect($execution)->not->toBeNull();
    expect($execution->status)->toBe(ExecutionStatus::Failed);
    expect($execution->error)->toContain('exceeded the maximum of 2 steps');
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

it('adopts pre-created execution from meta execution_id', function () {
    // Pre-create an execution (as queue dispatch would)
    $preCreated = Execution::create([
        'provider' => 'unknown',
        'model' => 'unknown',
        'status' => ExecutionStatus::Queued,
        'type' => ExecutionType::Text,
    ]);

    $middleware = app(TrackExecution::class);

    $agent = new class extends Agent
    {
        public function key(): string
        {
            return 'queue-agent';
        }
    };

    $context = new AgentContext(
        request: makeExecutionTextRequest(),
        agent: $agent,
        meta: ['execution_id' => $preCreated->id],
    );

    $result = $middleware->handle($context, fn () => makeExecutionFakeResult());

    // Should NOT have created a duplicate — same execution adopted
    expect(Execution::count())->toBe(1);
    expect($result->executionId)->toBe($preCreated->id);

    $execution = Execution::find($preCreated->id);
    expect($execution->status)->toBe(ExecutionStatus::Completed);
    expect($execution->agent)->toBe('queue-agent');
});

it('passes conversation_id through to adopted execution', function () {
    $conversation = Conversation::create([
        'agent' => 'test-agent',
    ]);

    $preCreated = Execution::create([
        'provider' => 'unknown',
        'model' => 'unknown',
        'status' => ExecutionStatus::Queued,
        'type' => ExecutionType::Text,
    ]);

    $middleware = app(TrackExecution::class);

    $context = new AgentContext(
        request: makeExecutionTextRequest(),
        meta: [
            'execution_id' => $preCreated->id,
            'conversation_id' => $conversation->id,
        ],
    );

    $middleware->handle($context, fn () => makeExecutionFakeResult());

    $execution = Execution::find($preCreated->id);
    expect($execution->conversation_id)->toBe($conversation->id);
});
