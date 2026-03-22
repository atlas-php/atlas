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
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Exceptions\MaxStepsExceededException;
use Atlasphp\Atlas\Executor\AgentExecutor;
use Atlasphp\Atlas\Executor\ToolExecutor;
use Atlasphp\Atlas\Executor\ToolRegistry;
use Atlasphp\Atlas\Messages\AssistantMessage;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Messages\ToolResultMessage;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;
use Atlasphp\Atlas\Tools\Tool;
use Illuminate\Contracts\Events\Dispatcher;

function makeTextRequest(): TextRequest
{
    return new TextRequest(
        model: 'test-model',
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

function makeFakeDispatcher(): Dispatcher
{
    return new class implements Dispatcher
    {
        /** @var array<int, object> */
        public array $dispatched = [];

        public function listen($events, $listener = null) {}

        public function hasListeners($eventName)
        {
            return false;
        }

        public function subscribe($subscriber) {}

        public function until($event, $payload = [])
        {
            return null;
        }

        public function dispatch($event, $payload = [], $halt = false)
        {
            if (is_object($event)) {
                $this->dispatched[] = $event;
            }

            return null;
        }

        public function push($event, $payload = []) {}

        public function flush($event) {}

        public function forget($event) {}

        public function forgetPushed() {}
    };
}

function makeEchoTool(): Tool
{
    return new class extends Tool
    {
        public function name(): string
        {
            return 'echo';
        }

        public function description(): string
        {
            return 'Echoes input.';
        }

        public function handle(array $args, array $context): mixed
        {
            return $args['text'] ?? 'echo';
        }
    };
}

function makeFailingTool(): Tool
{
    return new class extends Tool
    {
        public function name(): string
        {
            return 'fail';
        }

        public function description(): string
        {
            return 'Always fails.';
        }

        public function handle(array $args, array $context): mixed
        {
            throw new RuntimeException('Tool exploded');
        }
    };
}

function makeMockDriver(array $responses): Driver
{
    return new class($responses) extends Driver
    {
        private int $callIndex = 0;

        /** @var array<int, TextRequest> */
        public array $receivedRequests = [];

        public function __construct(private readonly array $responses)
        {
            // Skip parent constructor — no config/http needed for mock
        }

        public function text(TextRequest $request): TextResponse
        {
            $this->receivedRequests[] = $request;

            return $this->responses[$this->callIndex++];
        }

        public function capabilities(): ProviderCapabilities
        {
            throw new RuntimeException('Not implemented');
        }

        public function name(): string
        {
            return 'mock';
        }
    };
}

it('handles single round trip with no tools', function () {
    $driver = makeMockDriver([
        new TextResponse('Hello!', new Usage(10, 20), FinishReason::Stop),
    ]);

    $dispatcher = makeFakeDispatcher();
    $registry = new ToolRegistry([]);
    $toolExecutor = new ToolExecutor($registry);
    $executor = new AgentExecutor($driver, $toolExecutor, $dispatcher);

    $result = $executor->execute(makeTextRequest(), maxSteps: 10, concurrent: true, meta: []);

    expect($result->text)->toBe('Hello!');
    expect($result->totalSteps())->toBe(1);
    expect($result->totalToolCalls())->toBe(0);
    expect($result->finishReason)->toBe(FinishReason::Stop);
    expect($result->usage->inputTokens)->toBe(10);
    expect($result->usage->outputTokens)->toBe(20);

    // AgentCompleted should be dispatched
    $completed = array_filter($dispatcher->dispatched, fn ($e) => $e instanceof AgentCompleted);
    expect($completed)->toHaveCount(1);
});

it('handles one tool call across two round trips', function () {
    $driver = makeMockDriver([
        new TextResponse(
            'Let me search.',
            new Usage(10, 15),
            FinishReason::ToolCalls,
            toolCalls: [new ToolCall('tc-1', 'echo', ['text' => 'hello'])],
        ),
        new TextResponse('The result is hello.', new Usage(20, 25), FinishReason::Stop),
    ]);

    $dispatcher = makeFakeDispatcher();
    $echoTool = makeEchoTool();
    $registry = new ToolRegistry([$echoTool]);
    $toolExecutor = new ToolExecutor($registry);
    $executor = new AgentExecutor($driver, $toolExecutor, $dispatcher);

    $result = $executor->execute(makeTextRequest(), maxSteps: 10, concurrent: true, meta: []);

    expect($result->text)->toBe('The result is hello.');
    expect($result->totalSteps())->toBe(2);
    expect($result->totalToolCalls())->toBe(1);

    // Events: Calling, Called, Completed
    $eventClasses = array_map(fn ($e) => $e::class, $dispatcher->dispatched);
    expect($eventClasses)->toContain(AgentToolCallStarted::class);
    expect($eventClasses)->toContain(AgentToolCallCompleted::class);
    expect($eventClasses)->toContain(AgentCompleted::class);
});

it('executes multiple tool calls in single response sequentially', function () {
    $driver = makeMockDriver([
        new TextResponse(
            'Running tools.',
            new Usage(10, 10),
            FinishReason::ToolCalls,
            toolCalls: [
                new ToolCall('tc-1', 'echo', ['text' => 'a']),
                new ToolCall('tc-2', 'echo', ['text' => 'b']),
                new ToolCall('tc-3', 'echo', ['text' => 'c']),
            ],
        ),
        new TextResponse('Done.', new Usage(20, 20), FinishReason::Stop),
    ]);

    $dispatcher = makeFakeDispatcher();
    $registry = new ToolRegistry([makeEchoTool()]);
    $toolExecutor = new ToolExecutor($registry);
    $executor = new AgentExecutor($driver, $toolExecutor, $dispatcher);

    $result = $executor->execute(makeTextRequest(), maxSteps: 10, concurrent: false, meta: []);

    expect($result->totalSteps())->toBe(2);
    expect($result->totalToolCalls())->toBe(3);
    expect($result->steps[0]->toolResults)->toHaveCount(3);
    expect($result->steps[0]->toolResults[0]->content)->toBe('a');
    expect($result->steps[0]->toolResults[1]->content)->toBe('b');
    expect($result->steps[0]->toolResults[2]->content)->toBe('c');
});

it('falls back to sequential for single concurrent tool call', function () {
    $driver = makeMockDriver([
        new TextResponse(
            'Using tool.',
            new Usage(10, 10),
            FinishReason::ToolCalls,
            toolCalls: [new ToolCall('tc-1', 'echo', ['text' => 'solo'])],
        ),
        new TextResponse('Done.', new Usage(10, 10), FinishReason::Stop),
    ]);

    $dispatcher = makeFakeDispatcher();
    $registry = new ToolRegistry([makeEchoTool()]);
    $toolExecutor = new ToolExecutor($registry);
    $executor = new AgentExecutor($driver, $toolExecutor, $dispatcher);

    // concurrent: true with a single tool call falls back to sequential
    $result = $executor->execute(makeTextRequest(), maxSteps: 10, concurrent: true, meta: []);

    expect($result->totalSteps())->toBe(2);
    expect($result->totalToolCalls())->toBe(1);
    expect($result->steps[0]->toolResults[0]->content)->toBe('solo');
});

it('catches tool errors and sends error result to model', function () {
    $driver = makeMockDriver([
        new TextResponse(
            'Using tool.',
            new Usage(10, 10),
            FinishReason::ToolCalls,
            toolCalls: [new ToolCall('tc-1', 'fail', [])],
        ),
        new TextResponse('I see the tool failed.', new Usage(15, 15), FinishReason::Stop),
    ]);

    $dispatcher = makeFakeDispatcher();
    $registry = new ToolRegistry([makeFailingTool()]);
    $toolExecutor = new ToolExecutor($registry);
    $executor = new AgentExecutor($driver, $toolExecutor, $dispatcher);

    $result = $executor->execute(makeTextRequest(), maxSteps: 10, concurrent: true, meta: []);

    expect($result->text)->toBe('I see the tool failed.');
    expect($result->totalSteps())->toBe(2);

    // First step should have an error tool result
    $toolResult = $result->steps[0]->toolResults[0];
    expect($toolResult->isError)->toBeTrue();
    expect($toolResult->content)->toBe('Tool exploded');

    // AgentToolCallFailed event dispatched
    $errored = array_filter($dispatcher->dispatched, fn ($e) => $e instanceof AgentToolCallFailed);
    expect($errored)->toHaveCount(1);
});

it('throws MaxStepsExceededException when limit reached', function () {
    $driver = makeMockDriver([
        new TextResponse(
            'Tool 1.',
            new Usage(10, 10),
            FinishReason::ToolCalls,
            toolCalls: [new ToolCall('tc-1', 'echo', ['text' => 'a'])],
        ),
        new TextResponse(
            'Tool 2.',
            new Usage(10, 10),
            FinishReason::ToolCalls,
            toolCalls: [new ToolCall('tc-2', 'echo', ['text' => 'b'])],
        ),
        // Third call would exceed maxSteps of 2
    ]);

    $dispatcher = makeFakeDispatcher();
    $registry = new ToolRegistry([makeEchoTool()]);
    $toolExecutor = new ToolExecutor($registry);
    $executor = new AgentExecutor($driver, $toolExecutor, $dispatcher);

    $executor->execute(makeTextRequest(), maxSteps: 2, concurrent: true, meta: []);
})->throws(MaxStepsExceededException::class);

it('allows unlimited steps when maxSteps is null', function () {
    $driver = makeMockDriver([
        new TextResponse(
            'Step 1.',
            new Usage(5, 5),
            FinishReason::ToolCalls,
            toolCalls: [new ToolCall('tc-1', 'echo', ['text' => 'a'])],
        ),
        new TextResponse(
            'Step 2.',
            new Usage(5, 5),
            FinishReason::ToolCalls,
            toolCalls: [new ToolCall('tc-2', 'echo', ['text' => 'b'])],
        ),
        new TextResponse('Done.', new Usage(5, 5), FinishReason::Stop),
    ]);

    $dispatcher = makeFakeDispatcher();
    $registry = new ToolRegistry([makeEchoTool()]);
    $toolExecutor = new ToolExecutor($registry);
    $executor = new AgentExecutor($driver, $toolExecutor, $dispatcher);

    $result = $executor->execute(makeTextRequest(), maxSteps: null, concurrent: true, meta: []);

    expect($result->totalSteps())->toBe(3);
});

it('appends AssistantMessage and ToolResultMessages to next request', function () {
    $driver = makeMockDriver([
        new TextResponse(
            'Using echo.',
            new Usage(10, 10),
            FinishReason::ToolCalls,
            toolCalls: [new ToolCall('tc-1', 'echo', ['text' => 'ping'])],
        ),
        new TextResponse('pong', new Usage(10, 10), FinishReason::Stop),
    ]);

    $dispatcher = makeFakeDispatcher();
    $registry = new ToolRegistry([makeEchoTool()]);
    $toolExecutor = new ToolExecutor($registry);
    $executor = new AgentExecutor($driver, $toolExecutor, $dispatcher);

    $executor->execute(makeTextRequest(), maxSteps: 10, concurrent: true, meta: []);

    // Second request should have messages appended
    $secondRequest = $driver->receivedRequests[1];
    expect($secondRequest->messages)->toHaveCount(2);

    // First appended message is AssistantMessage
    $assistantMsg = $secondRequest->messages[0];
    expect($assistantMsg)->toBeInstanceOf(AssistantMessage::class);
    expect($assistantMsg->content)->toBe('Using echo.');

    // Second appended message is ToolResultMessage
    $toolResultMsg = $secondRequest->messages[1];
    expect($toolResultMsg)->toBeInstanceOf(ToolResultMessage::class);
    expect($toolResultMsg->toolCallId)->toBe('tc-1');
    expect($toolResultMsg->content)->toBe('ping');
});

it('merges usage across all steps', function () {
    $driver = makeMockDriver([
        new TextResponse(
            'Step 1.',
            new Usage(100, 50, reasoningTokens: 10),
            FinishReason::ToolCalls,
            toolCalls: [new ToolCall('tc-1', 'echo', ['text' => 'a'])],
        ),
        new TextResponse(
            'Done.',
            new Usage(200, 75, reasoningTokens: 20),
            FinishReason::Stop,
        ),
    ]);

    $dispatcher = makeFakeDispatcher();
    $registry = new ToolRegistry([makeEchoTool()]);
    $toolExecutor = new ToolExecutor($registry);
    $executor = new AgentExecutor($driver, $toolExecutor, $dispatcher);

    $result = $executor->execute(makeTextRequest(), maxSteps: 10, concurrent: true, meta: []);

    expect($result->usage->inputTokens)->toBe(300);
    expect($result->usage->outputTokens)->toBe(125);
    expect($result->usage->reasoningTokens)->toBe(30);
});

it('propagates provider exceptions', function () {
    $driver = new class extends Driver
    {
        public function __construct() {}

        public function text(TextRequest $request): TextResponse
        {
            throw new AtlasException('Provider failed');
        }

        public function capabilities(): ProviderCapabilities
        {
            throw new RuntimeException('Not implemented');
        }

        public function name(): string
        {
            return 'failing-mock';
        }
    };

    $dispatcher = makeFakeDispatcher();
    $registry = new ToolRegistry([]);
    $toolExecutor = new ToolExecutor($registry);
    $executor = new AgentExecutor($driver, $toolExecutor, $dispatcher);

    $executor->execute(makeTextRequest(), maxSteps: 10, concurrent: true, meta: []);
})->throws(AtlasException::class, 'Provider failed');

it('executes multiple tool calls concurrently with concurrent true', function () {
    $driver = makeMockDriver([
        new TextResponse(
            'Running tools.',
            new Usage(10, 10),
            FinishReason::ToolCalls,
            toolCalls: [
                new ToolCall('tc-1', 'echo', ['text' => 'a']),
                new ToolCall('tc-2', 'echo', ['text' => 'b']),
                new ToolCall('tc-3', 'echo', ['text' => 'c']),
            ],
        ),
        new TextResponse('Done.', new Usage(20, 20), FinishReason::Stop),
    ]);

    $dispatcher = makeFakeDispatcher();
    $registry = new ToolRegistry([makeEchoTool()]);
    $toolExecutor = new ToolExecutor($registry);
    $executor = new AgentExecutor($driver, $toolExecutor, $dispatcher);

    $result = $executor->execute(makeTextRequest(), maxSteps: 10, concurrent: true, meta: []);

    expect($result->totalSteps())->toBe(2);
    expect($result->totalToolCalls())->toBe(3);
    expect($result->steps[0]->toolResults)->toHaveCount(3);
    expect($result->steps[0]->toolResults[0]->content)->toBe('a');
    expect($result->steps[0]->toolResults[1]->content)->toBe('b');
    expect($result->steps[0]->toolResults[2]->content)->toBe('c');

    // Concurrent path fires AgentToolCallStarted events upfront, then AgentToolCallCompleted after
    $callingEvents = array_filter($dispatcher->dispatched, fn ($e) => $e instanceof AgentToolCallStarted);
    expect($callingEvents)->toHaveCount(3);

    $calledEvents = array_filter($dispatcher->dispatched, fn ($e) => $e instanceof AgentToolCallCompleted);
    expect($calledEvents)->toHaveCount(3);
});

it('handles errors in concurrent path with multiple tools', function () {
    $driver = makeMockDriver([
        new TextResponse(
            'Running tools.',
            new Usage(10, 10),
            FinishReason::ToolCalls,
            toolCalls: [
                new ToolCall('tc-1', 'echo', ['text' => 'a']),
                new ToolCall('tc-2', 'fail', []),
            ],
        ),
        new TextResponse('One failed.', new Usage(20, 20), FinishReason::Stop),
    ]);

    $dispatcher = makeFakeDispatcher();
    $registry = new ToolRegistry([makeEchoTool(), makeFailingTool()]);
    $toolExecutor = new ToolExecutor($registry);
    $executor = new AgentExecutor($driver, $toolExecutor, $dispatcher);

    $result = $executor->execute(makeTextRequest(), maxSteps: 10, concurrent: true, meta: []);

    expect($result->totalSteps())->toBe(2);
    expect($result->steps[0]->toolResults)->toHaveCount(2);
    expect($result->steps[0]->toolResults[0]->content)->toBe('a');
    expect($result->steps[0]->toolResults[0]->isError)->toBeFalse();
    expect($result->steps[0]->toolResults[1]->content)->toBe('Tool exploded');
    expect($result->steps[0]->toolResults[1]->isError)->toBeTrue();

    $erroredEvents = array_filter($dispatcher->dispatched, fn ($e) => $e instanceof AgentToolCallFailed);
    expect($erroredEvents)->toHaveCount(1);
});

it('catches all tool errors in concurrent path when every tool fails', function () {
    $driver = makeMockDriver([
        new TextResponse(
            'Running tools.',
            new Usage(10, 10),
            FinishReason::ToolCalls,
            toolCalls: [
                new ToolCall('tc-1', 'fail', []),
                new ToolCall('tc-2', 'fail', []),
            ],
        ),
        new TextResponse('Both failed.', new Usage(20, 20), FinishReason::Stop),
    ]);

    $dispatcher = makeFakeDispatcher();
    $registry = new ToolRegistry([makeFailingTool()]);
    $toolExecutor = new ToolExecutor($registry);
    $executor = new AgentExecutor($driver, $toolExecutor, $dispatcher);

    $result = $executor->execute(makeTextRequest(), maxSteps: 10, concurrent: true, meta: []);

    expect($result->totalSteps())->toBe(2);
    expect($result->steps[0]->toolResults)->toHaveCount(2);
    expect($result->steps[0]->toolResults[0]->isError)->toBeTrue();
    expect($result->steps[0]->toolResults[0]->content)->toBe('Tool exploded');
    expect($result->steps[0]->toolResults[1]->isError)->toBeTrue();
    expect($result->steps[0]->toolResults[1]->content)->toBe('Tool exploded');

    $erroredEvents = array_filter($dispatcher->dispatched, fn ($e) => $e instanceof AgentToolCallFailed);
    expect($erroredEvents)->toHaveCount(2);
});

it('executes multiple tool calls sequentially when concurrent is false', function () {
    $driver = makeMockDriver([
        new TextResponse(
            'Running tools.',
            new Usage(10, 10),
            FinishReason::ToolCalls,
            toolCalls: [
                new ToolCall('tc-1', 'echo', ['text' => 'a']),
                new ToolCall('tc-2', 'echo', ['text' => 'b']),
            ],
        ),
        new TextResponse('Done.', new Usage(20, 20), FinishReason::Stop),
    ]);

    $dispatcher = makeFakeDispatcher();
    $registry = new ToolRegistry([makeEchoTool()]);
    $toolExecutor = new ToolExecutor($registry);
    $executor = new AgentExecutor($driver, $toolExecutor, $dispatcher);

    $result = $executor->execute(makeTextRequest(), maxSteps: 10, concurrent: false, meta: []);

    expect($result->totalSteps())->toBe(2);
    expect($result->totalToolCalls())->toBe(2);
    expect($result->steps[0]->toolResults)->toHaveCount(2);
    expect($result->steps[0]->toolResults[0]->content)->toBe('a');
    expect($result->steps[0]->toolResults[1]->content)->toBe('b');

    $callingEvents = array_filter($dispatcher->dispatched, fn ($e) => $e instanceof AgentToolCallStarted);
    expect($callingEvents)->toHaveCount(2);

    $calledEvents = array_filter($dispatcher->dispatched, fn ($e) => $e instanceof AgentToolCallCompleted);
    expect($calledEvents)->toHaveCount(2);
});

it('handles mixed success and error with multiple sequential tools', function () {
    $driver = makeMockDriver([
        new TextResponse(
            'Running tools.',
            new Usage(10, 10),
            FinishReason::ToolCalls,
            toolCalls: [
                new ToolCall('tc-1', 'echo', ['text' => 'a']),
                new ToolCall('tc-2', 'fail', []),
            ],
        ),
        new TextResponse('One failed.', new Usage(20, 20), FinishReason::Stop),
    ]);

    $dispatcher = makeFakeDispatcher();
    $registry = new ToolRegistry([makeEchoTool(), makeFailingTool()]);
    $toolExecutor = new ToolExecutor($registry);
    $executor = new AgentExecutor($driver, $toolExecutor, $dispatcher);

    $result = $executor->execute(makeTextRequest(), maxSteps: 10, concurrent: false, meta: []);

    expect($result->totalSteps())->toBe(2);
    expect($result->steps[0]->toolResults)->toHaveCount(2);
    expect($result->steps[0]->toolResults[0]->content)->toBe('a');
    expect($result->steps[0]->toolResults[0]->isError)->toBeFalse();
    expect($result->steps[0]->toolResults[1]->content)->toBe('Tool exploded');
    expect($result->steps[0]->toolResults[1]->isError)->toBeTrue();

    $erroredEvents = array_filter($dispatcher->dispatched, fn ($e) => $e instanceof AgentToolCallFailed);
    expect($erroredEvents)->toHaveCount(1);
});

it('dispatches AgentCompleted with correct steps', function () {
    $driver = makeMockDriver([
        new TextResponse(
            'Using tool.',
            new Usage(10, 10),
            FinishReason::ToolCalls,
            toolCalls: [new ToolCall('tc-1', 'echo', ['text' => 'x'])],
        ),
        new TextResponse('Final.', new Usage(10, 10), FinishReason::Stop),
    ]);

    $dispatcher = makeFakeDispatcher();
    $registry = new ToolRegistry([makeEchoTool()]);
    $toolExecutor = new ToolExecutor($registry);
    $executor = new AgentExecutor($driver, $toolExecutor, $dispatcher);

    $result = $executor->execute(makeTextRequest(), maxSteps: 10, concurrent: true, meta: []);

    $completed = array_values(array_filter($dispatcher->dispatched, fn ($e) => $e instanceof AgentCompleted));
    expect($completed)->toHaveCount(1);
    expect($completed[0]->steps)->toHaveCount(2);
    expect($completed[0]->steps)->toBe($result->steps);
});

// ─── Concurrent closure catch block (sync driver) ───────────────────────────

it('concurrent closure catch converts exception to error ToolResult via sync driver', function () {
    $driver = makeMockDriver([
        new TextResponse(
            'Running tools.',
            new Usage(10, 10),
            FinishReason::ToolCalls,
            toolCalls: [
                new ToolCall('tc-1', 'echo', ['text' => 'ok']),
                new ToolCall('tc-2', 'fail', []),
            ],
        ),
        new TextResponse('Done.', new Usage(20, 20), FinishReason::Stop),
    ]);

    $dispatcher = makeFakeDispatcher();
    $registry = new ToolRegistry([makeEchoTool(), makeFailingTool()]);
    $toolExecutor = new ToolExecutor($registry);

    // Force sync driver so the closure runs in-process and the catch block is hit directly
    $executor = new class($driver, $toolExecutor, $dispatcher) extends AgentExecutor
    {
        protected function concurrencyDriver(): string
        {
            return 'sync';
        }
    };

    $result = $executor->execute(makeTextRequest(), maxSteps: 10, concurrent: true, meta: []);

    // The failing tool's exception is caught inside the closure, returned as ToolResult
    expect($result->steps[0]->toolResults)->toHaveCount(2);
    expect($result->steps[0]->toolResults[0]->content)->toBe('ok');
    expect($result->steps[0]->toolResults[0]->isError)->toBeFalse();
    expect($result->steps[0]->toolResults[1]->content)->toBe('Tool exploded');
    expect($result->steps[0]->toolResults[1]->isError)->toBeTrue();

    // AgentToolCallFailed fires for the failed tool
    $erroredEvents = array_filter($dispatcher->dispatched, fn ($e) => $e instanceof AgentToolCallFailed);
    expect($erroredEvents)->toHaveCount(1);
});

it('concurrencyDriver returns sync when fork is unavailable', function () {
    $driver = makeMockDriver([
        new TextResponse(
            'Running tools.',
            new Usage(10, 10),
            FinishReason::ToolCalls,
            toolCalls: [
                new ToolCall('tc-1', 'echo', ['text' => 'a']),
                new ToolCall('tc-2', 'echo', ['text' => 'b']),
            ],
        ),
        new TextResponse('Done.', new Usage(20, 20), FinishReason::Stop),
    ]);

    $dispatcher = makeFakeDispatcher();
    $registry = new ToolRegistry([makeEchoTool()]);
    $toolExecutor = new ToolExecutor($registry);

    // Simulate environment where Fork class doesn't exist
    $executor = new class($driver, $toolExecutor, $dispatcher) extends AgentExecutor
    {
        protected function concurrencyDriver(): string
        {
            // Mimic the real logic but pretend Fork class doesn't exist
            if (false && extension_loaded('pcntl')) {
                return 'fork';
            }

            return 'sync';
        }
    };

    $result = $executor->execute(makeTextRequest(), maxSteps: 10, concurrent: true, meta: []);

    // Tools still execute correctly via sync driver
    expect($result->totalSteps())->toBe(2);
    expect($result->totalToolCalls())->toBe(2);
    expect($result->steps[0]->toolResults[0]->content)->toBe('a');
    expect($result->steps[0]->toolResults[1]->content)->toBe('b');
});

// ─── Dispatch-site lifecycle events ─────────────────────────────────────────

it('dispatches AgentStarted before the tool loop', function () {
    $driver = makeMockDriver([
        new TextResponse('Done.', new Usage(10, 20), FinishReason::Stop),
    ]);

    $dispatcher = makeFakeDispatcher();
    $registry = new ToolRegistry([]);
    $toolExecutor = new ToolExecutor($registry);
    $executor = new AgentExecutor($driver, $toolExecutor, $dispatcher);

    $executor->execute(makeTextRequest(), maxSteps: 10, concurrent: true, meta: [], agentKey: 'test-agent');

    $started = array_filter($dispatcher->dispatched, fn ($e) => $e instanceof AgentStarted);
    expect($started)->toHaveCount(1);
    $event = array_values($started)[0];
    expect($event->agentKey)->toBe('test-agent');
    expect($event->maxSteps)->toBe(10);
    expect($event->concurrent)->toBeTrue();
});

it('dispatches AgentStepStarted and AgentStepCompleted for each step', function () {
    $driver = makeMockDriver([
        new TextResponse(
            'Using tool.',
            new Usage(10, 10),
            FinishReason::ToolCalls,
            toolCalls: [new ToolCall('tc-1', 'echo', ['text' => 'a'])],
        ),
        new TextResponse('Done.', new Usage(10, 10), FinishReason::Stop),
    ]);

    $dispatcher = makeFakeDispatcher();
    $registry = new ToolRegistry([makeEchoTool()]);
    $toolExecutor = new ToolExecutor($registry);
    $executor = new AgentExecutor($driver, $toolExecutor, $dispatcher);

    $executor->execute(makeTextRequest(), maxSteps: 10, concurrent: true, meta: []);

    $stepStarted = array_filter($dispatcher->dispatched, fn ($e) => $e instanceof AgentStepStarted);
    $stepCompleted = array_filter($dispatcher->dispatched, fn ($e) => $e instanceof AgentStepCompleted);
    expect($stepStarted)->toHaveCount(2);
    expect($stepCompleted)->toHaveCount(2);

    $stepStartedArr = array_values($stepStarted);
    expect($stepStartedArr[0]->stepNumber)->toBe(1)
        ->and($stepStartedArr[0]->agentKey)->toBeNull();
    expect($stepStartedArr[1]->stepNumber)->toBe(2);

    $stepCompletedArr = array_values($stepCompleted);
    expect($stepCompletedArr[0]->stepNumber)->toBe(1);
    expect($stepCompletedArr[0]->finishReason)->toBe(FinishReason::ToolCalls);
    expect($stepCompletedArr[0]->agentKey)->toBeNull();
    expect($stepCompletedArr[1]->stepNumber)->toBe(2);
    expect($stepCompletedArr[1]->finishReason)->toBe(FinishReason::Stop);
});

it('dispatches AgentMaxStepsExceeded before throwing exception', function () {
    $driver = makeMockDriver([
        new TextResponse(
            'Tool 1.',
            new Usage(10, 10),
            FinishReason::ToolCalls,
            toolCalls: [new ToolCall('tc-1', 'echo', ['text' => 'a'])],
        ),
        new TextResponse(
            'Tool 2.',
            new Usage(10, 10),
            FinishReason::ToolCalls,
            toolCalls: [new ToolCall('tc-2', 'echo', ['text' => 'b'])],
        ),
    ]);

    $dispatcher = makeFakeDispatcher();
    $registry = new ToolRegistry([makeEchoTool()]);
    $toolExecutor = new ToolExecutor($registry);
    $executor = new AgentExecutor($driver, $toolExecutor, $dispatcher);

    try {
        $executor->execute(makeTextRequest(), maxSteps: 2, concurrent: true, meta: []);
    } catch (MaxStepsExceededException) {
        // expected
    }

    $exceeded = array_filter($dispatcher->dispatched, fn ($e) => $e instanceof AgentMaxStepsExceeded);
    expect($exceeded)->toHaveCount(1);
    $event = array_values($exceeded)[0];
    expect($event->limit)->toBe(2);
    expect($event->steps)->toHaveCount(2);

    // AgentCompleted fires via finally block even on exception
    $completed = array_filter($dispatcher->dispatched, fn ($e) => $e instanceof AgentCompleted);
    expect($completed)->toHaveCount(1);
});

it('dispatches events in correct lifecycle order', function () {
    $driver = makeMockDriver([
        new TextResponse(
            'Using tool.',
            new Usage(10, 10),
            FinishReason::ToolCalls,
            toolCalls: [new ToolCall('tc-1', 'echo', ['text' => 'a'])],
        ),
        new TextResponse('Done.', new Usage(10, 10), FinishReason::Stop),
    ]);

    $dispatcher = makeFakeDispatcher();
    $registry = new ToolRegistry([makeEchoTool()]);
    $toolExecutor = new ToolExecutor($registry);
    $executor = new AgentExecutor($driver, $toolExecutor, $dispatcher);

    $executor->execute(makeTextRequest(), maxSteps: 10, concurrent: true, meta: [], agentKey: 'order-test');

    $eventClasses = array_map(fn ($e) => $e::class, $dispatcher->dispatched);

    // Expected order: AgentStarted → Step1Started → Step1Completed → ToolCallStarted → ToolCallCompleted → Step2Started → Step2Completed → AgentCompleted
    expect($eventClasses)->toBe([
        AgentStarted::class,
        AgentStepStarted::class,
        AgentStepCompleted::class,
        AgentToolCallStarted::class,
        AgentToolCallCompleted::class,
        AgentStepStarted::class,
        AgentStepCompleted::class,
        AgentCompleted::class,
    ]);

    // Verify AgentCompleted carries agentKey and usage
    $completed = array_values(array_filter($dispatcher->dispatched, fn ($e) => $e instanceof AgentCompleted));
    expect($completed[0]->agentKey)->toBe('order-test');
    expect($completed[0]->usage->inputTokens)->toBe(20);  // 10 + 10 from two steps
});
