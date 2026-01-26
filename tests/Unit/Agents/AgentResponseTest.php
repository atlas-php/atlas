<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Support\AgentContext;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Illuminate\Support\Collection;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Structured\Response as StructuredResponse;
use Prism\Prism\Text\Response as PrismResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\ToolCall;
use Prism\Prism\ValueObjects\ToolResult;
use Prism\Prism\ValueObjects\Usage;

function makePrismResponse(
    string $text = 'Test response',
    array $toolCalls = [],
    array $toolResults = [],
    ?Usage $usage = null,
    ?Meta $meta = null,
): PrismResponse {
    return new PrismResponse(
        steps: new Collection,
        text: $text,
        finishReason: FinishReason::Stop,
        toolCalls: $toolCalls,
        toolResults: $toolResults,
        usage: $usage ?? new Usage(10, 20),
        meta: $meta ?? new Meta('req-123', 'gpt-4'),
        messages: new Collection,
    );
}

function makeStructuredResponse(
    array $structured = ['name' => 'John'],
    ?Usage $usage = null,
    ?Meta $meta = null,
): StructuredResponse {
    return new StructuredResponse(
        steps: new Collection,
        text: '',
        structured: $structured,
        finishReason: FinishReason::Stop,
        usage: $usage ?? new Usage(10, 20),
        meta: $meta ?? new Meta('req-123', 'gpt-4'),
    );
}

// === Constructor and Properties ===

test('AgentResponse stores all constructor parameters', function () {
    $prismResponse = makePrismResponse();
    $agent = new TestAgent;
    $context = new AgentContext(
        variables: ['name' => 'John'],
        metadata: ['user_id' => 123],
    );

    $response = new AgentResponse(
        response: $prismResponse,
        agent: $agent,
        input: 'Hello world',
        systemPrompt: 'You are a helpful assistant.',
        context: $context,
    );

    expect($response->response)->toBe($prismResponse);
    expect($response->agent)->toBe($agent);
    expect($response->input)->toBe('Hello world');
    expect($response->systemPrompt)->toBe('You are a helpful assistant.');
    expect($response->context)->toBe($context);
});

test('AgentResponse accepts null systemPrompt', function () {
    $prismResponse = makePrismResponse();
    $agent = new TestAgent;
    $context = new AgentContext;

    $response = new AgentResponse(
        response: $prismResponse,
        agent: $agent,
        input: 'Hello',
        systemPrompt: null,
        context: $context,
    );

    expect($response->systemPrompt)->toBeNull();
});

// === Magic __get Delegation (Backward Compatibility) ===

test('__get delegates to PrismResponse properties', function () {
    $usage = new Usage(50, 100);
    $meta = new Meta('test-id', 'gpt-4-turbo');
    $prismResponse = makePrismResponse('Hello there!', usage: $usage, meta: $meta);

    $response = new AgentResponse(
        response: $prismResponse,
        agent: new TestAgent,
        input: 'Hi',
        systemPrompt: null,
        context: new AgentContext,
    );

    // Property access via __get should work
    expect($response->text)->toBe('Hello there!');
    expect($response->usage)->toBe($usage);
    expect($response->meta)->toBe($meta);
    expect($response->finishReason)->toBe(FinishReason::Stop);
});

test('__get works with StructuredResponse properties', function () {
    $structuredResponse = makeStructuredResponse(['status' => 'ok']);

    $response = new AgentResponse(
        response: $structuredResponse,
        agent: new TestAgent,
        input: 'Extract status',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response->structured)->toBe(['status' => 'ok']);
    expect($response->text)->toBe('');
});

// === Explicit Accessor Methods ===

test('text() returns the text response', function () {
    $prismResponse = makePrismResponse('The answer is 42');

    $response = new AgentResponse(
        response: $prismResponse,
        agent: new TestAgent,
        input: 'Question',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response->text())->toBe('The answer is 42');
});

test('usage() returns usage statistics', function () {
    $usage = new Usage(100, 200);
    $prismResponse = makePrismResponse(usage: $usage);

    $response = new AgentResponse(
        response: $prismResponse,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response->usage())->toBe($usage);
    expect($response->usage()->promptTokens)->toBe(100);
    expect($response->usage()->completionTokens)->toBe(200);
});

test('toolCalls() returns tool calls array', function () {
    $toolCall = new ToolCall(
        id: 'call_123',
        name: 'calculator',
        arguments: ['a' => 5, 'b' => 3],
    );
    $prismResponse = makePrismResponse(toolCalls: [$toolCall]);

    $response = new AgentResponse(
        response: $prismResponse,
        agent: new TestAgent,
        input: 'Add 5 and 3',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response->toolCalls())->toHaveCount(1);
    expect($response->toolCalls()[0]->name)->toBe('calculator');
});

test('toolCalls() returns empty array for StructuredResponse', function () {
    $structuredResponse = makeStructuredResponse();

    $response = new AgentResponse(
        response: $structuredResponse,
        agent: new TestAgent,
        input: 'Extract',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response->toolCalls())->toBe([]);
});

test('toolResults() returns tool results array', function () {
    $toolResult = new ToolResult(
        toolCallId: 'call_123',
        toolName: 'calculator',
        args: ['a' => 5, 'b' => 3],
        result: '8',
    );
    $prismResponse = makePrismResponse(toolResults: [$toolResult]);

    $response = new AgentResponse(
        response: $prismResponse,
        agent: new TestAgent,
        input: 'Add numbers',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response->toolResults())->toHaveCount(1);
    expect($response->toolResults()[0]->result)->toBe('8');
});

test('toolResults() returns empty array for StructuredResponse', function () {
    $structuredResponse = makeStructuredResponse();

    $response = new AgentResponse(
        response: $structuredResponse,
        agent: new TestAgent,
        input: 'Extract',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response->toolResults())->toBe([]);
});

test('steps() returns the steps collection', function () {
    $prismResponse = makePrismResponse();

    $response = new AgentResponse(
        response: $prismResponse,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response->steps())->toBeInstanceOf(Collection::class);
});

test('finishReason() returns the finish reason', function () {
    $prismResponse = makePrismResponse();

    $response = new AgentResponse(
        response: $prismResponse,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response->finishReason())->toBe(FinishReason::Stop);
});

test('meta() returns response metadata', function () {
    $meta = new Meta('unique-id', 'claude-3');
    $prismResponse = makePrismResponse(meta: $meta);

    $response = new AgentResponse(
        response: $prismResponse,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response->meta())->toBe($meta);
    expect($response->meta()->id)->toBe('unique-id');
    expect($response->meta()->model)->toBe('claude-3');
});

test('messages() returns the messages collection', function () {
    $prismResponse = makePrismResponse();

    $response = new AgentResponse(
        response: $prismResponse,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response->messages())->toBeInstanceOf(Collection::class);
});

// === Structured Output Detection ===

test('isStructured() returns false for PrismResponse', function () {
    $prismResponse = makePrismResponse();

    $response = new AgentResponse(
        response: $prismResponse,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response->isStructured())->toBeFalse();
});

test('isStructured() returns true for StructuredResponse', function () {
    $structuredResponse = makeStructuredResponse();

    $response = new AgentResponse(
        response: $structuredResponse,
        agent: new TestAgent,
        input: 'Extract data',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response->isStructured())->toBeTrue();
});

test('structured() returns null for PrismResponse', function () {
    $prismResponse = makePrismResponse();

    $response = new AgentResponse(
        response: $prismResponse,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response->structured())->toBeNull();
});

test('structured() returns data for StructuredResponse', function () {
    $structuredResponse = makeStructuredResponse([
        'name' => 'John Doe',
        'age' => 30,
        'active' => true,
    ]);

    $response = new AgentResponse(
        response: $structuredResponse,
        agent: new TestAgent,
        input: 'Extract person',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response->structured())->toBe([
        'name' => 'John Doe',
        'age' => 30,
        'active' => true,
    ]);
});

// === Agent-Specific Accessors ===

test('agentKey() returns the agent key', function () {
    $agent = new TestAgent;
    $prismResponse = makePrismResponse();

    $response = new AgentResponse(
        response: $prismResponse,
        agent: $agent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response->agentKey())->toBe($agent->key());
});

test('agentName() returns the agent name', function () {
    $agent = new TestAgent;
    $prismResponse = makePrismResponse();

    $response = new AgentResponse(
        response: $prismResponse,
        agent: $agent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response->agentName())->toBe($agent->name());
});

test('agentDescription() returns the agent description', function () {
    $agent = new TestAgent;
    $prismResponse = makePrismResponse();

    $response = new AgentResponse(
        response: $prismResponse,
        agent: $agent,
        input: 'Hello',
        systemPrompt: null,
        context: new AgentContext,
    );

    expect($response->agentDescription())->toBe($agent->description());
});

test('metadata() returns context metadata', function () {
    $context = new AgentContext(
        metadata: ['user_id' => 123, 'session_id' => 'abc'],
    );
    $prismResponse = makePrismResponse();

    $response = new AgentResponse(
        response: $prismResponse,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: $context,
    );

    expect($response->metadata())->toBe(['user_id' => 123, 'session_id' => 'abc']);
});

test('metadata() returns empty array when none set', function () {
    $context = new AgentContext;
    $prismResponse = makePrismResponse();

    $response = new AgentResponse(
        response: $prismResponse,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: $context,
    );

    expect($response->metadata())->toBe([]);
});

test('variables() returns context variables', function () {
    $context = new AgentContext(
        variables: ['customer_name' => 'Jane', 'tier' => 'premium'],
    );
    $prismResponse = makePrismResponse();

    $response = new AgentResponse(
        response: $prismResponse,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: $context,
    );

    expect($response->variables())->toBe(['customer_name' => 'Jane', 'tier' => 'premium']);
});

test('variables() returns empty array when none set', function () {
    $context = new AgentContext;
    $prismResponse = makePrismResponse();

    $response = new AgentResponse(
        response: $prismResponse,
        agent: new TestAgent,
        input: 'Hello',
        systemPrompt: null,
        context: $context,
    );

    expect($response->variables())->toBe([]);
});
