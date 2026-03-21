<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Middleware\StepContext;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Persistence\Middleware\TrackStep;
use Atlasphp\Atlas\Persistence\Models\ExecutionStep;
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

function makeTextResponse(array $toolCalls = [], ?string $reasoning = null): TextResponse
{
    return new TextResponse(
        text: 'Hello world',
        usage: new Usage(10, 5),
        finishReason: FinishReason::Stop,
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
