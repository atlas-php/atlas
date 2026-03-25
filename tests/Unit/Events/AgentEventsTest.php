<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Events\AgentCompleted;
use Atlasphp\Atlas\Events\AgentMaxStepsExceeded;
use Atlasphp\Atlas\Events\AgentStarted;
use Atlasphp\Atlas\Events\AgentStepCompleted;
use Atlasphp\Atlas\Events\AgentStepStarted;
use Atlasphp\Atlas\Events\AgentToolCallCompleted;
use Atlasphp\Atlas\Events\AgentToolCallFailed;
use Atlasphp\Atlas\Events\AgentToolCallStarted;
use Atlasphp\Atlas\Executor\Step;
use Atlasphp\Atlas\Executor\ToolResult;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Responses\Usage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

// ─── AgentStarted ──────────────────────────────────────────────────────────

it('AgentStarted stores agentKey, maxSteps, concurrent', function () {
    $event = new AgentStarted(
        agentKey: 'my-agent',
        maxSteps: 10,
        concurrent: true,
    );

    expect($event->agentKey)->toBe('my-agent')
        ->and($event->maxSteps)->toBe(10)
        ->and($event->concurrent)->toBeTrue();
});

it('AgentStarted accepts null agentKey and maxSteps', function () {
    $event = new AgentStarted(
        agentKey: null,
        maxSteps: null,
        concurrent: true,
    );

    expect($event->agentKey)->toBeNull()
        ->and($event->maxSteps)->toBeNull();
});

it('AgentStarted stores concurrent as false', function () {
    $event = new AgentStarted(
        agentKey: 'sequential-agent',
        maxSteps: 5,
        concurrent: false,
    );

    expect($event->concurrent)->toBeFalse();
});

// ─── AgentStepStarted ──────────────────────────────────────────────────────

it('AgentStepStarted stores stepNumber', function () {
    $event = new AgentStepStarted(stepNumber: 3);

    expect($event->stepNumber)->toBe(3)
        ->and($event->agentKey)->toBeNull();
});

// ─── AgentStepCompleted ────────────────────────────────────────────────────

it('AgentStepCompleted stores stepNumber, finishReason, usage', function () {
    $usage = new Usage(10, 20);

    $event = new AgentStepCompleted(
        stepNumber: 2,
        finishReason: FinishReason::Stop,
        usage: $usage,
    );

    expect($event->stepNumber)->toBe(2)
        ->and($event->finishReason)->toBe(FinishReason::Stop)
        ->and($event->usage)->toBe($usage)
        ->and($event->agentKey)->toBeNull();
});

it('AgentStepCompleted stores ToolCalls finish reason', function () {
    $usage = new Usage(15, 25);

    $event = new AgentStepCompleted(
        stepNumber: 1,
        finishReason: FinishReason::ToolCalls,
        usage: $usage,
    );

    expect($event->finishReason)->toBe(FinishReason::ToolCalls);
});

// ─── AgentMaxStepsExceeded ─────────────────────────────────────────────────

it('AgentMaxStepsExceeded stores limit and steps', function () {
    $step = new Step(text: 'hello', toolCalls: [], toolResults: [], usage: new Usage(10, 20));
    $steps = [$step];

    $event = new AgentMaxStepsExceeded(limit: 5, steps: $steps);

    expect($event->limit)->toBe(5)
        ->and($event->steps)->toBe($steps)
        ->and($event->steps)->toHaveCount(1)
        ->and($event->agentKey)->toBeNull();
});

it('AgentMaxStepsExceeded accepts empty steps array', function () {
    $event = new AgentMaxStepsExceeded(limit: 3, steps: []);

    expect($event->limit)->toBe(3)
        ->and($event->steps)->toBe([])
        ->and($event->agentKey)->toBeNull();
});

// ─── AgentCompleted ────────────────────────────────────────────────────────

it('AgentCompleted stores steps', function () {
    $step = new Step(text: 'done', toolCalls: [], toolResults: [], usage: new Usage(10, 20));
    $steps = [$step];

    $usage = new Usage(10, 20);
    $event = new AgentCompleted(steps: $steps, usage: $usage);

    expect($event->steps)->toBe($steps)
        ->and($event->steps)->toHaveCount(1)
        ->and($event->usage)->toBe($usage)
        ->and($event->agentKey)->toBeNull();
});

it('AgentCompleted accepts empty steps array', function () {
    $event = new AgentCompleted(steps: [], usage: new Usage(0, 0), agentKey: 'test-agent');

    expect($event->steps)->toBe([])
        ->and($event->usage->inputTokens)->toBe(0)
        ->and($event->agentKey)->toBe('test-agent');
});

// ─── AgentToolCallStarted ──────────────────────────────────────────────────

it('AgentToolCallStarted stores toolCall', function () {
    $toolCall = new ToolCall('tc-1', 'search', ['q' => 'test']);

    $event = new AgentToolCallStarted(toolCall: $toolCall);

    expect($event->toolCall)->toBe($toolCall)
        ->and($event->toolCall->id)->toBe('tc-1')
        ->and($event->toolCall->name)->toBe('search')
        ->and($event->toolCall->arguments)->toBe(['q' => 'test'])
        ->and($event->agentKey)->toBeNull()
        ->and($event->stepNumber)->toBeNull();
});

// ─── AgentToolCallCompleted ────────────────────────────────────────────────

it('AgentToolCallCompleted stores toolCall and result', function () {
    $toolCall = new ToolCall('tc-1', 'search', ['q' => 'test']);
    $result = new ToolResult(toolCall: $toolCall, content: 'result', isError: false);

    $event = new AgentToolCallCompleted(toolCall: $toolCall, result: $result);

    expect($event->toolCall)->toBe($toolCall)
        ->and($event->result)->toBe($result)
        ->and($event->result->content)->toBe('result')
        ->and($event->result->isError)->toBeFalse()
        ->and($event->agentKey)->toBeNull()
        ->and($event->stepNumber)->toBeNull();
});

it('AgentToolCallCompleted stores error result', function () {
    $toolCall = new ToolCall('tc-2', 'fetch', ['url' => 'http://example.com']);
    $result = new ToolResult(toolCall: $toolCall, content: 'Connection refused', isError: true);

    $event = new AgentToolCallCompleted(toolCall: $toolCall, result: $result);

    expect($event->result->isError)->toBeTrue()
        ->and($event->result->content)->toBe('Connection refused');
});

// ─── AgentToolCallFailed ───────────────────────────────────────────────────

it('AgentToolCallFailed stores toolCall and exception', function () {
    $toolCall = new ToolCall('tc-1', 'search', ['q' => 'test']);
    $exception = new RuntimeException('Tool execution failed');

    $event = new AgentToolCallFailed(toolCall: $toolCall, exception: $exception);

    expect($event->toolCall)->toBe($toolCall)
        ->and($event->exception)->toBe($exception)
        ->and($event->exception->getMessage())->toBe('Tool execution failed')
        ->and($event->agentKey)->toBeNull()
        ->and($event->stepNumber)->toBeNull();
});

it('AgentToolCallFailed accepts any Throwable', function () {
    $toolCall = new ToolCall('tc-3', 'calculate', ['expr' => '1/0']);
    $error = new Error('Division by zero');

    $event = new AgentToolCallFailed(toolCall: $toolCall, exception: $error);

    expect($event->exception)->toBeInstanceOf(Throwable::class)
        ->and($event->exception->getMessage())->toBe('Division by zero');
});

// ─── AgentCompleted with agentKey ─────────────────────────────────────────

it('AgentCompleted stores agentKey and usage', function () {
    $usage = new Usage(100, 200);
    $event = new AgentCompleted(steps: [], usage: $usage, agentKey: 'my-agent');

    expect($event->agentKey)->toBe('my-agent')
        ->and($event->usage->inputTokens)->toBe(100)
        ->and($event->usage->outputTokens)->toBe(200);
});

// ─── AgentCompleted finishReason ──────────────────────────────────────────

it('AgentCompleted stores finishReason when passed', function () {
    $event = new AgentCompleted(
        steps: [],
        usage: new Usage(10, 20),
        finishReason: FinishReason::Stop,
    );

    expect($event->finishReason)->toBe(FinishReason::Stop);
});

it('AgentCompleted finishReason defaults to null', function () {
    $event = new AgentCompleted(steps: [], usage: new Usage(0, 0));

    expect($event->finishReason)->toBeNull();
});

// ─── Context params: provider, model, traceId ─────────────────────────────

it('AgentStarted stores provider, model, and traceId when passed', function () {
    $event = new AgentStarted(
        agentKey: 'ctx-agent',
        maxSteps: 5,
        concurrent: false,
        provider: 'openai',
        model: 'gpt-4o',
        traceId: 'trace-001',
    );

    expect($event->provider)->toBe('openai')
        ->and($event->model)->toBe('gpt-4o')
        ->and($event->traceId)->toBe('trace-001');
});

it('AgentStarted context params default to null', function () {
    $event = new AgentStarted(agentKey: null, maxSteps: null, concurrent: true);

    expect($event->provider)->toBeNull()
        ->and($event->model)->toBeNull()
        ->and($event->traceId)->toBeNull();
});

it('AgentStepStarted stores provider, model, and traceId when passed', function () {
    $event = new AgentStepStarted(
        stepNumber: 1,
        provider: 'anthropic',
        model: 'claude-4',
        traceId: 'trace-002',
    );

    expect($event->provider)->toBe('anthropic')
        ->and($event->model)->toBe('claude-4')
        ->and($event->traceId)->toBe('trace-002');
});

it('AgentStepStarted context params default to null', function () {
    $event = new AgentStepStarted(stepNumber: 1);

    expect($event->provider)->toBeNull()
        ->and($event->model)->toBeNull()
        ->and($event->traceId)->toBeNull();
});

it('AgentStepCompleted stores provider, model, and traceId when passed', function () {
    $event = new AgentStepCompleted(
        stepNumber: 2,
        finishReason: FinishReason::Stop,
        usage: new Usage(10, 20),
        provider: 'openai',
        model: 'gpt-4o',
        traceId: 'trace-003',
    );

    expect($event->provider)->toBe('openai')
        ->and($event->model)->toBe('gpt-4o')
        ->and($event->traceId)->toBe('trace-003');
});

it('AgentStepCompleted context params default to null', function () {
    $event = new AgentStepCompleted(
        stepNumber: 1,
        finishReason: FinishReason::Stop,
        usage: new Usage(10, 20),
    );

    expect($event->provider)->toBeNull()
        ->and($event->model)->toBeNull()
        ->and($event->traceId)->toBeNull();
});

it('AgentMaxStepsExceeded stores provider, model, and traceId when passed', function () {
    $event = new AgentMaxStepsExceeded(
        limit: 5,
        steps: [],
        provider: 'anthropic',
        model: 'claude-4',
        traceId: 'trace-004',
    );

    expect($event->provider)->toBe('anthropic')
        ->and($event->model)->toBe('claude-4')
        ->and($event->traceId)->toBe('trace-004');
});

it('AgentMaxStepsExceeded context params default to null', function () {
    $event = new AgentMaxStepsExceeded(limit: 3, steps: []);

    expect($event->provider)->toBeNull()
        ->and($event->model)->toBeNull()
        ->and($event->traceId)->toBeNull();
});

it('AgentCompleted stores provider, model, and traceId when passed', function () {
    $event = new AgentCompleted(
        steps: [],
        usage: new Usage(10, 20),
        provider: 'openai',
        model: 'gpt-4o',
        traceId: 'trace-005',
    );

    expect($event->provider)->toBe('openai')
        ->and($event->model)->toBe('gpt-4o')
        ->and($event->traceId)->toBe('trace-005');
});

it('AgentCompleted context params default to null', function () {
    $event = new AgentCompleted(steps: [], usage: new Usage(0, 0));

    expect($event->provider)->toBeNull()
        ->and($event->model)->toBeNull()
        ->and($event->traceId)->toBeNull();
});

it('AgentToolCallStarted stores provider, model, and traceId when passed', function () {
    $toolCall = new ToolCall('tc-ctx', 'search', ['q' => 'test']);

    $event = new AgentToolCallStarted(
        toolCall: $toolCall,
        provider: 'anthropic',
        model: 'claude-4',
        traceId: 'trace-006',
    );

    expect($event->provider)->toBe('anthropic')
        ->and($event->model)->toBe('claude-4')
        ->and($event->traceId)->toBe('trace-006');
});

it('AgentToolCallStarted context params default to null', function () {
    $toolCall = new ToolCall('tc-ctx2', 'search', []);
    $event = new AgentToolCallStarted(toolCall: $toolCall);

    expect($event->provider)->toBeNull()
        ->and($event->model)->toBeNull()
        ->and($event->traceId)->toBeNull();
});

it('AgentToolCallCompleted stores provider, model, and traceId when passed', function () {
    $toolCall = new ToolCall('tc-ctx', 'search', ['q' => 'test']);
    $result = new ToolResult(toolCall: $toolCall, content: 'ok', isError: false);

    $event = new AgentToolCallCompleted(
        toolCall: $toolCall,
        result: $result,
        provider: 'openai',
        model: 'gpt-4o',
        traceId: 'trace-007',
    );

    expect($event->provider)->toBe('openai')
        ->and($event->model)->toBe('gpt-4o')
        ->and($event->traceId)->toBe('trace-007');
});

it('AgentToolCallCompleted context params default to null', function () {
    $toolCall = new ToolCall('tc-ctx2', 'search', []);
    $result = new ToolResult(toolCall: $toolCall, content: 'ok', isError: false);
    $event = new AgentToolCallCompleted(toolCall: $toolCall, result: $result);

    expect($event->provider)->toBeNull()
        ->and($event->model)->toBeNull()
        ->and($event->traceId)->toBeNull();
});

it('AgentToolCallFailed stores provider, model, and traceId when passed', function () {
    $toolCall = new ToolCall('tc-ctx', 'search', []);
    $exception = new RuntimeException('fail');

    $event = new AgentToolCallFailed(
        toolCall: $toolCall,
        exception: $exception,
        provider: 'anthropic',
        model: 'claude-4',
        traceId: 'trace-008',
    );

    expect($event->provider)->toBe('anthropic')
        ->and($event->model)->toBe('claude-4')
        ->and($event->traceId)->toBe('trace-008');
});

it('AgentToolCallFailed context params default to null', function () {
    $toolCall = new ToolCall('tc-ctx2', 'search', []);
    $exception = new RuntimeException('fail');
    $event = new AgentToolCallFailed(toolCall: $toolCall, exception: $exception);

    expect($event->provider)->toBeNull()
        ->and($event->model)->toBeNull()
        ->and($event->traceId)->toBeNull();
});

// ─── Broadcasting ─────────────────────────────────────────────────────────

it('all orchestration events implement ShouldBroadcastNow', function () {
    $channel = new Channel('test');
    $toolCall = new ToolCall('tc-1', 'search', []);

    $events = [
        new AgentStarted(agentKey: null, maxSteps: null, concurrent: false, channel: $channel),
        new AgentStepStarted(stepNumber: 1, channel: $channel),
        new AgentStepCompleted(stepNumber: 1, finishReason: FinishReason::Stop, usage: new Usage(10, 20), channel: $channel),
        new AgentToolCallStarted(toolCall: $toolCall, channel: $channel),
        new AgentToolCallCompleted(toolCall: $toolCall, result: new ToolResult($toolCall, 'ok'), channel: $channel),
        new AgentToolCallFailed(toolCall: $toolCall, exception: new RuntimeException('fail'), channel: $channel),
        new AgentCompleted(steps: [], usage: new Usage(0, 0), channel: $channel),
        new AgentMaxStepsExceeded(limit: 3, steps: [], channel: $channel),
    ];

    foreach ($events as $event) {
        expect($event)->toBeInstanceOf(ShouldBroadcastNow::class);
    }
});

it('orchestration events broadcast on provided channel', function () {
    $channel = new Channel('conversation.42');
    $event = new AgentToolCallStarted(
        toolCall: new ToolCall('tc-1', 'search', ['q' => 'test']),
        channel: $channel,
    );

    expect($event->broadcastOn())->toBe([$channel])
        ->and($event->broadcastWhen())->toBeTrue()
        ->and($event->broadcastAs())->toBe('AgentToolCallStarted');
});

it('orchestration events do not broadcast without channel', function () {
    $event = new AgentToolCallStarted(
        toolCall: new ToolCall('tc-1', 'search', []),
    );

    expect($event->broadcastOn())->toBe([])
        ->and($event->broadcastWhen())->toBeFalse();
});

it('AgentToolCallStarted broadcastWith includes tool details', function () {
    $event = new AgentToolCallStarted(
        toolCall: new ToolCall('tc-1', 'web_search', ['query' => 'Laravel']),
        agentKey: 'my-agent',
        stepNumber: 2,
        channel: new Channel('test'),
    );

    $data = $event->broadcastWith();

    expect($data['agentKey'])->toBe('my-agent')
        ->and($data['toolCallId'])->toBe('tc-1')
        ->and($data['toolName'])->toBe('web_search')
        ->and($data['arguments'])->toBe(['query' => 'Laravel'])
        ->and($data['stepNumber'])->toBe(2);
});

it('AgentToolCallStarted truncates large string arguments', function () {
    $event = new AgentToolCallStarted(
        toolCall: new ToolCall('tc-1', 'process', ['content' => str_repeat('x', 500)]),
        channel: new Channel('test'),
    );

    $data = $event->broadcastWith();

    expect(mb_strlen($data['arguments']['content']))->toBe(200);
});

it('AgentToolCallCompleted broadcastWith includes result summary', function () {
    $toolCall = new ToolCall('tc-1', 'search', []);
    $result = new ToolResult(toolCall: $toolCall, content: 'Found 5 results');

    $event = new AgentToolCallCompleted(
        toolCall: $toolCall,
        result: $result,
        agentKey: 'my-agent',
        stepNumber: 1,
        channel: new Channel('test'),
    );

    $data = $event->broadcastWith();

    expect($data['agentKey'])->toBe('my-agent')
        ->and($data['toolCallId'])->toBe('tc-1')
        ->and($data['toolName'])->toBe('search')
        ->and($data['result'])->toBe('Found 5 results')
        ->and($data['isError'])->toBeFalse()
        ->and($data['stepNumber'])->toBe(1);
});

it('AgentToolCallFailed broadcastWith includes error message', function () {
    $toolCall = new ToolCall('tc-1', 'fetch', []);
    $exception = new RuntimeException('Connection refused');

    $event = new AgentToolCallFailed(
        toolCall: $toolCall,
        exception: $exception,
        agentKey: 'my-agent',
        stepNumber: 3,
        channel: new Channel('test'),
    );

    $data = $event->broadcastWith();

    expect($data['agentKey'])->toBe('my-agent')
        ->and($data['toolCallId'])->toBe('tc-1')
        ->and($data['toolName'])->toBe('fetch')
        ->and($data['error'])->toBe('Connection refused')
        ->and($data['stepNumber'])->toBe(3);
});

// ─── broadcastAs ──────────────────────────────────────────────────────────

it('each orchestration event has correct broadcastAs', function () {
    $channel = new Channel('test');
    $toolCall = new ToolCall('tc-1', 'search', []);

    $expectations = [
        [new AgentStarted(agentKey: null, maxSteps: null, concurrent: false, channel: $channel), 'AgentStarted'],
        [new AgentStepStarted(stepNumber: 1, channel: $channel), 'AgentStepStarted'],
        [new AgentStepCompleted(stepNumber: 1, finishReason: FinishReason::Stop, usage: new Usage(0, 0), channel: $channel), 'AgentStepCompleted'],
        [new AgentToolCallStarted(toolCall: $toolCall, channel: $channel), 'AgentToolCallStarted'],
        [new AgentToolCallCompleted(toolCall: $toolCall, result: new ToolResult($toolCall, 'ok'), channel: $channel), 'AgentToolCallCompleted'],
        [new AgentToolCallFailed(toolCall: $toolCall, exception: new RuntimeException('fail'), channel: $channel), 'AgentToolCallFailed'],
        [new AgentCompleted(steps: [], usage: new Usage(0, 0), channel: $channel), 'AgentCompleted'],
        [new AgentMaxStepsExceeded(limit: 3, steps: [], channel: $channel), 'AgentMaxStepsExceeded'],
    ];

    foreach ($expectations as [$event, $expected]) {
        expect($event->broadcastAs())->toBe($expected);
    }
});

// ─── broadcastWith for all events ─────────────────────────────────────────

it('AgentStarted broadcastWith includes agentKey, maxSteps, concurrent', function () {
    $event = new AgentStarted(
        agentKey: 'my-agent',
        maxSteps: 10,
        concurrent: true,
        provider: 'openai',
        model: 'gpt-4o',
        traceId: 'trace-001',
        channel: new Channel('test'),
    );

    $data = $event->broadcastWith();

    expect($data)->toBe([
        'agentKey' => 'my-agent',
        'maxSteps' => 10,
        'concurrent' => true,
    ]);
});

it('AgentStarted broadcastWith excludes provider, model, traceId', function () {
    $event = new AgentStarted(
        agentKey: 'test',
        maxSteps: 5,
        concurrent: false,
        provider: 'openai',
        model: 'gpt-4o',
        traceId: 'trace-secret',
        channel: new Channel('test'),
    );

    $data = $event->broadcastWith();

    expect($data)->not->toHaveKey('provider')
        ->and($data)->not->toHaveKey('model')
        ->and($data)->not->toHaveKey('traceId');
});

it('AgentStepStarted broadcastWith includes agentKey and stepNumber', function () {
    $event = new AgentStepStarted(
        stepNumber: 3,
        agentKey: 'my-agent',
        channel: new Channel('test'),
    );

    $data = $event->broadcastWith();

    expect($data)->toBe([
        'agentKey' => 'my-agent',
        'stepNumber' => 3,
    ]);
});

it('AgentStepCompleted broadcastWith includes step context without usage', function () {
    $event = new AgentStepCompleted(
        stepNumber: 2,
        finishReason: FinishReason::ToolCalls,
        usage: new Usage(100, 200),
        agentKey: 'my-agent',
        channel: new Channel('test'),
    );

    $data = $event->broadcastWith();

    expect($data)->toBe([
        'agentKey' => 'my-agent',
        'stepNumber' => 2,
        'finishReason' => 'tool_calls',
    ]);
});

it('AgentCompleted broadcastWith includes summary without raw steps', function () {
    $step1 = new Step(text: null, toolCalls: [], toolResults: [], usage: new Usage(50, 100));
    $step2 = new Step(text: 'done', toolCalls: [], toolResults: [], usage: new Usage(50, 100));

    $event = new AgentCompleted(
        steps: [$step1, $step2],
        usage: new Usage(100, 200),
        agentKey: 'my-agent',
        finishReason: FinishReason::Stop,
        channel: new Channel('test'),
    );

    $data = $event->broadcastWith();

    expect($data)->toBe([
        'agentKey' => 'my-agent',
        'stepCount' => 2,
        'usage' => [
            'inputTokens' => 100,
            'outputTokens' => 200,
        ],
        'finishReason' => 'stop',
    ]);
});

it('AgentCompleted broadcastWith handles null finishReason', function () {
    $event = new AgentCompleted(
        steps: [],
        usage: new Usage(0, 0),
        agentKey: 'my-agent',
        channel: new Channel('test'),
    );

    expect($event->broadcastWith()['finishReason'])->toBeNull();
});

it('AgentMaxStepsExceeded broadcastWith includes summary without raw steps', function () {
    $step = new Step(text: null, toolCalls: [], toolResults: [], usage: new Usage(10, 20));

    $event = new AgentMaxStepsExceeded(
        limit: 5,
        steps: [$step, $step, $step],
        agentKey: 'my-agent',
        channel: new Channel('test'),
    );

    $data = $event->broadcastWith();

    expect($data)->toBe([
        'agentKey' => 'my-agent',
        'limit' => 5,
        'stepCount' => 3,
    ]);
});

// ─── broadcastWith truncation ─────────────────────────────────────────────

it('AgentToolCallCompleted broadcastWith truncates large result content', function () {
    $toolCall = new ToolCall('tc-1', 'search', []);
    $result = new ToolResult(toolCall: $toolCall, content: str_repeat('x', 1000));

    $event = new AgentToolCallCompleted(
        toolCall: $toolCall,
        result: $result,
        channel: new Channel('test'),
    );

    $data = $event->broadcastWith();

    expect(mb_strlen($data['result']))->toBe(500);
});

it('AgentToolCallFailed broadcastWith truncates large error message', function () {
    $toolCall = new ToolCall('tc-1', 'fetch', []);
    $exception = new RuntimeException(str_repeat('e', 1000));

    $event = new AgentToolCallFailed(
        toolCall: $toolCall,
        exception: $exception,
        channel: new Channel('test'),
    );

    $data = $event->broadcastWith();

    expect(mb_strlen($data['error']))->toBe(500);
});

it('AgentToolCallStarted broadcastWith does not truncate non-string arguments', function () {
    $event = new AgentToolCallStarted(
        toolCall: new ToolCall('tc-1', 'calculate', ['value' => 42, 'enabled' => true]),
        channel: new Channel('test'),
    );

    $data = $event->broadcastWith();

    expect($data['arguments']['value'])->toBe(42)
        ->and($data['arguments']['enabled'])->toBeTrue();
});

// ─── channel defaults ─────────────────────────────────────────────────────

it('channel defaults to null on all orchestration events', function () {
    $toolCall = new ToolCall('tc-1', 'search', []);

    $events = [
        new AgentStarted(agentKey: null, maxSteps: null, concurrent: false),
        new AgentStepStarted(stepNumber: 1),
        new AgentStepCompleted(stepNumber: 1, finishReason: FinishReason::Stop, usage: new Usage(0, 0)),
        new AgentToolCallStarted(toolCall: $toolCall),
        new AgentToolCallCompleted(toolCall: $toolCall, result: new ToolResult($toolCall, 'ok')),
        new AgentToolCallFailed(toolCall: $toolCall, exception: new RuntimeException('fail')),
        new AgentCompleted(steps: [], usage: new Usage(0, 0)),
        new AgentMaxStepsExceeded(limit: 3, steps: []),
    ];

    foreach ($events as $event) {
        expect($event->broadcastWhen())->toBeFalse()
            ->and($event->broadcastOn())->toBe([]);
    }
});
