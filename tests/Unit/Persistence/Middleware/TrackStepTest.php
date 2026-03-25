<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Middleware\StepContext;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Enums\ToolCallType;
use Atlasphp\Atlas\Persistence\Middleware\TrackStep;
use Atlasphp\Atlas\Persistence\Models\Execution;
use Atlasphp\Atlas\Persistence\Models\ExecutionStep;
use Atlasphp\Atlas\Persistence\Models\ExecutionToolCall;
use Atlasphp\Atlas\Persistence\Services\ExecutionService;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;

function makeStepTextRequest(): TextRequest
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

function makeStepContext(): StepContext
{
    return new StepContext(
        stepNumber: 1,
        request: makeStepTextRequest(),
        accumulatedUsage: new Usage(0, 0),
        previousSteps: [],
        meta: [],
    );
}

function makeTextResponse(array $toolCalls = [], ?string $reasoning = null, ?FinishReason $finishReason = null): TextResponse
{
    return new TextResponse(
        text: 'Hello world',
        usage: new Usage(10, 5),
        finishReason: $finishReason ?? ($toolCalls !== [] ? FinishReason::ToolCalls : FinishReason::Stop),
        toolCalls: $toolCalls,
        reasoning: $reasoning,
    );
}

function makeServiceWithExecution(): ExecutionService
{
    $service = new ExecutionService;
    $service->createExecution(
        provider: 'openai',
        model: 'gpt-5',
        type: ExecutionType::Text,
    );
    $service->beginExecution();

    return $service;
}

it('skips when no active execution', function () {
    $service = new ExecutionService;
    $middleware = new TrackStep($service);

    $context = makeStepContext();
    $response = makeTextResponse();

    $result = $middleware->handle($context, fn () => $response);

    expect($result)->toBe($response);
    expect(ExecutionStep::count())->toBe(0);
});

it('creates step and records response on success', function () {
    $service = makeServiceWithExecution();
    $middleware = new TrackStep($service);

    $context = makeStepContext();
    $response = makeTextResponse();

    $middleware->handle($context, fn () => $response);

    $step = ExecutionStep::latest('id')->first();

    expect($step)->not->toBeNull();
    expect($step->status)->toBe(ExecutionStatus::Completed);
    expect($step->content)->toBe('Hello world');
    expect($step->input_tokens)->toBe(10);
    expect($step->output_tokens)->toBe(5);
    expect($step->finish_reason)->toBe('stop');
});

it('sets finish_reason to tool_calls when response has tool calls', function () {
    $service = makeServiceWithExecution();
    $middleware = new TrackStep($service);

    $context = makeStepContext();
    $toolCalls = [new ToolCall(id: 'call_1', name: 'search', arguments: ['q' => 'test'])];
    $response = makeTextResponse(toolCalls: $toolCalls);

    $middleware->handle($context, fn () => $response);

    $step = ExecutionStep::latest('id')->first();
    expect($step->finish_reason)->toBe('tool_calls');
});

it('sets finish_reason to stop when no tool calls', function () {
    $service = makeServiceWithExecution();
    $middleware = new TrackStep($service);

    $context = makeStepContext();
    $response = makeTextResponse();

    $middleware->handle($context, fn () => $response);

    $step = ExecutionStep::latest('id')->first();
    expect($step->finish_reason)->toBe('stop');
});

it('records reasoning from response', function () {
    $service = makeServiceWithExecution();
    $middleware = new TrackStep($service);

    $context = makeStepContext();
    $response = makeTextResponse(reasoning: 'I thought about it carefully');

    $middleware->handle($context, fn () => $response);

    $step = ExecutionStep::latest('id')->first();
    expect($step->reasoning)->toBe('I thought about it carefully');
});

it('re-throws exceptions', function () {
    $service = makeServiceWithExecution();
    $middleware = new TrackStep($service);

    $context = makeStepContext();

    $middleware->handle($context, function () {
        throw new RuntimeException('Step failed');
    });
})->throws(RuntimeException::class, 'Step failed');

it('step is marked failed when execution fails after step exception', function () {
    $service = makeServiceWithExecution();
    $middleware = new TrackStep($service);
    $context = makeStepContext();

    try {
        $middleware->handle($context, function () {
            throw new RuntimeException('Provider crashed');
        });
    } catch (RuntimeException) {
        // Expected — step middleware re-throws
    }

    // Simulate what TrackExecution would do on catching the exception
    $service->failExecution(new RuntimeException('Provider crashed'));

    $step = ExecutionStep::latest('id')->first();
    expect($step->status)->toBe(ExecutionStatus::Failed);

    $execution = Execution::latest('id')->first();
    expect($execution->status)->toBe(ExecutionStatus::Failed);
});

it('logs provider tool calls from response', function () {
    $service = makeServiceWithExecution();
    $middleware = new TrackStep($service);

    $context = makeStepContext();
    $response = new TextResponse(
        text: 'PHP 8.4 was released.',
        usage: new Usage(10, 5),
        finishReason: FinishReason::Stop,
        providerToolCalls: [
            ['type' => 'web_search_call', 'id' => 'ws_1', 'status' => 'completed', 'action' => ['type' => 'search', 'query' => 'PHP 8.4']],
            ['type' => 'code_interpreter_call', 'id' => 'ci_1', 'status' => 'completed'],
        ],
    );

    $middleware->handle($context, fn () => $response);

    $toolCalls = ExecutionToolCall::all();

    expect($toolCalls)->toHaveCount(2);

    expect($toolCalls[0]->name)->toBe('web_search_call');
    expect($toolCalls[0]->tool_call_id)->toBe('ws_1');
    expect($toolCalls[0]->type)->toBe(ToolCallType::Provider);
    expect($toolCalls[0]->status)->toBe(ExecutionStatus::Completed);

    expect($toolCalls[1]->name)->toBe('code_interpreter_call');
    expect($toolCalls[1]->tool_call_id)->toBe('ci_1');
    expect($toolCalls[1]->type)->toBe(ToolCallType::Provider);
    expect($toolCalls[1]->status)->toBe(ExecutionStatus::Completed);
});

it('marks failed provider tool calls as failed', function () {
    $service = makeServiceWithExecution();
    $middleware = new TrackStep($service);

    $context = makeStepContext();
    $response = new TextResponse(
        text: 'Search failed.',
        usage: new Usage(10, 5),
        finishReason: FinishReason::Stop,
        providerToolCalls: [
            ['type' => 'web_search_call', 'id' => 'ws_1', 'status' => 'failed'],
        ],
    );

    $middleware->handle($context, fn () => $response);

    $toolCall = ExecutionToolCall::first();

    expect($toolCall)->not->toBeNull();
    expect($toolCall->type)->toBe(ToolCallType::Provider);
    expect($toolCall->status)->toBe(ExecutionStatus::Failed);
});

it('handles provider tool calls with missing optional fields', function () {
    $service = makeServiceWithExecution();
    $middleware = new TrackStep($service);

    $context = makeStepContext();
    $response = new TextResponse(
        text: 'Result',
        usage: new Usage(10, 5),
        finishReason: FinishReason::Stop,
        providerToolCalls: [
            ['type' => 'web_search_call'],  // No id or status
        ],
    );

    $middleware->handle($context, fn () => $response);

    $toolCall = ExecutionToolCall::first();

    expect($toolCall)->not->toBeNull();
    expect($toolCall->name)->toBe('web_search_call');
    expect($toolCall->tool_call_id)->toBe('');
    expect($toolCall->type)->toBe(ToolCallType::Provider);
    expect($toolCall->status)->toBe(ExecutionStatus::Completed);
});

it('skips provider tool logging when no provider tools in response', function () {
    $service = makeServiceWithExecution();
    $middleware = new TrackStep($service);

    $context = makeStepContext();
    $response = makeTextResponse();

    $middleware->handle($context, fn () => $response);

    expect(ExecutionToolCall::count())->toBe(0);
});

it('marks step failed directly without TrackExecution wrapping', function () {
    // Create execution and step manually — no TrackExecution middleware involved
    $service = makeServiceWithExecution();
    $middleware = new TrackStep($service);

    $context = makeStepContext();

    try {
        $middleware->handle($context, function () {
            throw new RuntimeException('Provider crashed');
        });
    } catch (RuntimeException) {
        // Expected — step middleware re-throws
    }

    // The step should be marked failed directly by TrackStep's catch block,
    // without relying on TrackExecution's failExecution() call.
    $step = ExecutionStep::latest('id')->first();

    expect($step)->not->toBeNull();
    expect($step->status)->toBe(ExecutionStatus::Failed);
    expect($step->error)->toContain('Provider crashed');
});
