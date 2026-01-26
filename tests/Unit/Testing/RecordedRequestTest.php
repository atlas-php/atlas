<?php

declare(strict_types=1);

use Atlasphp\Atlas\Agents\Support\AgentContext;
use Atlasphp\Atlas\Agents\Support\AgentResponse;
use Atlasphp\Atlas\Testing\Support\RecordedRequest;
use Atlasphp\Atlas\Tests\Fixtures\TestAgent;
use Illuminate\Support\Collection;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Text\Response as PrismResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

function createAgentResponse(string $text, $agent, string $input, AgentContext $context): AgentResponse
{
    $prismResponse = new PrismResponse(
        steps: new Collection([]),
        text: $text,
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(10, 5),
        meta: new Meta('req-123', 'gpt-4'),
        messages: new Collection([]),
    );

    return new AgentResponse(
        response: $prismResponse,
        agent: $agent,
        input: $input,
        systemPrompt: null,
        context: $context,
    );
}

test('it captures agent, input, context, and AgentResponse', function () {
    $agent = new TestAgent;
    $input = 'Hello, AI!';
    $context = new AgentContext(
        variables: ['user_name' => 'John'],
        metadata: ['session_id' => 'abc123'],
    );
    $agentResponse = createAgentResponse('Hello, John!', $agent, $input, $context);

    $recorded = new RecordedRequest(
        agent: $agent,
        input: $input,
        context: $context,
        response: $agentResponse,
        timestamp: time(),
    );

    expect($recorded->agent)->toBe($agent);
    expect($recorded->input)->toBe($input);
    expect($recorded->context)->toBe($context);
    expect($recorded->response)->toBe($agentResponse);
    expect($recorded->response->text)->toBe('Hello, John!');
});

test('agentKey returns agent key', function () {
    $agent = new TestAgent;
    $context = new AgentContext;
    $agentResponse = createAgentResponse('', $agent, 'Test', $context);

    $recorded = new RecordedRequest(
        agent: $agent,
        input: 'Test',
        context: $context,
        response: $agentResponse,
        timestamp: time(),
    );

    expect($recorded->agentKey())->toBe('test-agent');
});

test('inputContains checks input string', function () {
    $agent = new TestAgent;
    $context = new AgentContext;
    $input = 'What is the weather in New York?';
    $agentResponse = createAgentResponse('', $agent, $input, $context);

    $recorded = new RecordedRequest(
        agent: $agent,
        input: $input,
        context: $context,
        response: $agentResponse,
        timestamp: time(),
    );

    expect($recorded->inputContains('weather'))->toBeTrue();
    expect($recorded->inputContains('New York'))->toBeTrue();
    expect($recorded->inputContains('London'))->toBeFalse();
});

test('hasMetadata checks context metadata', function () {
    $agent = new TestAgent;
    $context = new AgentContext(
        metadata: ['session_id' => 'abc123', 'user_id' => 42],
    );
    $agentResponse = createAgentResponse('', $agent, 'Test', $context);

    $recorded = new RecordedRequest(
        agent: $agent,
        input: 'Test',
        context: $context,
        response: $agentResponse,
        timestamp: time(),
    );

    // Check key exists
    expect($recorded->hasMetadata('session_id'))->toBeTrue();
    expect($recorded->hasMetadata('missing_key'))->toBeFalse();

    // Check key and value match
    expect($recorded->hasMetadata('session_id', 'abc123'))->toBeTrue();
    expect($recorded->hasMetadata('session_id', 'wrong'))->toBeFalse();
    expect($recorded->hasMetadata('user_id', 42))->toBeTrue();
});

test('hasPrismCall checks prism calls in context', function () {
    $agent = new TestAgent;
    $context = new AgentContext(
        prismCalls: [
            ['method' => 'withMaxSteps', 'args' => [10]],
            ['method' => 'withTemperature', 'args' => [0.7]],
        ],
    );
    $agentResponse = createAgentResponse('', $agent, 'Test', $context);

    $recorded = new RecordedRequest(
        agent: $agent,
        input: 'Test',
        context: $context,
        response: $agentResponse,
        timestamp: time(),
    );

    expect($recorded->hasPrismCall('withMaxSteps'))->toBeTrue();
    expect($recorded->hasPrismCall('withTemperature'))->toBeTrue();
    expect($recorded->hasPrismCall('withSchema'))->toBeFalse();
});

test('getPrismCallArgs returns arguments for prism call', function () {
    $agent = new TestAgent;
    $context = new AgentContext(
        prismCalls: [
            ['method' => 'withMaxSteps', 'args' => [10]],
        ],
    );
    $agentResponse = createAgentResponse('', $agent, 'Test', $context);

    $recorded = new RecordedRequest(
        agent: $agent,
        input: 'Test',
        context: $context,
        response: $agentResponse,
        timestamp: time(),
    );

    expect($recorded->getPrismCallArgs('withMaxSteps'))->toBe([10]);
    expect($recorded->getPrismCallArgs('withSchema'))->toBeNull();
});
