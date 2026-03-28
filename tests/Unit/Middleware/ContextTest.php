<?php

declare(strict_types=1);

use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Middleware\AgentContext;
use Atlasphp\Atlas\Middleware\ProviderContext;
use Atlasphp\Atlas\Middleware\StepContext;
use Atlasphp\Atlas\Middleware\ToolContext;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\Usage;

function makeMiddlewareTextRequest(): TextRequest
{
    return new TextRequest(
        model: 'gpt-4o',
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

it('ProviderContext stores all properties', function () {
    $request = makeMiddlewareTextRequest();

    $ctx = new ProviderContext(
        provider: 'openai',
        model: 'gpt-4o',
        method: 'text',
        request: $request,
        meta: ['key' => 'value'],
    );

    expect($ctx->provider)->toBe('openai');
    expect($ctx->model)->toBe('gpt-4o');
    expect($ctx->method)->toBe('text');
    expect($ctx->request)->toBe($request);
    expect($ctx->meta)->toBe(['key' => 'value']);
});

it('ProviderContext meta defaults to empty', function () {
    $ctx = new ProviderContext(
        provider: 'openai',
        model: 'gpt-4o',
        method: 'text',
        request: makeMiddlewareTextRequest(),
    );

    expect($ctx->meta)->toBe([]);
});

it('StepContext stores all properties', function () {
    $request = makeMiddlewareTextRequest();
    $usage = new Usage(100, 50);

    $ctx = new StepContext(
        stepNumber: 3,
        request: $request,
        accumulatedUsage: $usage,
        previousSteps: ['step1', 'step2'],
        meta: ['agent' => 'research'],
    );

    expect($ctx->stepNumber)->toBe(3);
    expect($ctx->request)->toBe($request);
    expect($ctx->accumulatedUsage)->toBe($usage);
    expect($ctx->previousSteps)->toHaveCount(2);
    expect($ctx->meta)->toBe(['agent' => 'research']);
});

it('ToolContext stores all properties', function () {
    $toolCall = new ToolCall('tc-1', 'search', ['q' => 'test']);

    $ctx = new ToolContext(
        toolCall: $toolCall,
        meta: ['user' => 'tim'],
    );

    expect($ctx->toolCall)->toBe($toolCall);
    expect($ctx->toolCall->name)->toBe('search');
    expect($ctx->meta)->toBe(['user' => 'tim']);
});

it('AgentContext stores all properties', function () {
    $request = makeMiddlewareTextRequest();

    $ctx = new AgentContext(
        request: $request,
        tools: ['tool1', 'tool2'],
        meta: ['session' => 'abc'],
    );

    expect($ctx->request)->toBe($request);
    expect($ctx->tools)->toBe(['tool1', 'tool2']);
    expect($ctx->meta)->toBe(['session' => 'abc']);
});

it('ProviderContext request is mutable', function () {
    $ctx = new ProviderContext(
        provider: 'openai',
        model: 'gpt-4o',
        method: 'text',
        request: makeMiddlewareTextRequest(),
    );

    $newRequest = makeMiddlewareTextRequest();
    $ctx->request = $newRequest;

    expect($ctx->request)->toBe($newRequest);
});

it('StepContext request is mutable', function () {
    $ctx = new StepContext(
        stepNumber: 1,
        request: makeMiddlewareTextRequest(),
        accumulatedUsage: new Usage(0, 0),
    );

    $newRequest = makeMiddlewareTextRequest();
    $ctx->request = $newRequest;

    expect($ctx->request)->toBe($newRequest);
});
