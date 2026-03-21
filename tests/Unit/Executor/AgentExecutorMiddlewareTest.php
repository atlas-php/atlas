<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Executor\AgentExecutor;
use Atlasphp\Atlas\Executor\ToolExecutor;
use Atlasphp\Atlas\Executor\ToolRegistry;
use Atlasphp\Atlas\Messages\ToolCall;
use Atlasphp\Atlas\Middleware\MiddlewareStack;
use Atlasphp\Atlas\Middleware\StepContext;
use Atlasphp\Atlas\Middleware\ToolContext;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;
use Atlasphp\Atlas\Tools\Tool;
use Illuminate\Contracts\Events\Dispatcher;

function makeMiddlewareExecutorRequest(): TextRequest
{
    return new TextRequest(
        model: 'gpt-4o',
        instructions: 'Be helpful',
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

function makeMiddlewareExecutorDispatcher(): Dispatcher
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

        public function until($event, $payload = []) {}

        public function dispatch($event, $payload = [], $halt = false)
        {
            $this->dispatched[] = $event;
        }

        public function push($event, $payload = []) {}

        public function flush($event) {}

        public function forget($event) {}

        public function forgetPushed() {}
    };
}

function makeMiddlewareEchoTool(): Tool
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

        public function handle(array $args, array $context = []): mixed
        {
            return $args['input'] ?? 'echoed';
        }
    };
}

function makeMiddlewareMockDriver(array $responses): Driver
{
    return new class($responses) extends Driver
    {
        private int $callIndex = 0;

        public function __construct(private readonly array $responseList)
        {
            // Skip parent constructor
        }

        public function name(): string
        {
            return 'mock';
        }

        public function capabilities(): ProviderCapabilities
        {
            return new ProviderCapabilities(text: true);
        }

        public function text(TextRequest $request): TextResponse
        {
            return $this->responseList[$this->callIndex++];
        }
    };
}

it('runs step middleware on each step', function () {
    $stepNumbers = [];

    config()->set('atlas.middleware.step', [
        new class($stepNumbers)
        {
            public function __construct(private array &$stepNumbers) {}

            public function handle(StepContext $context, Closure $next)
            {
                $this->stepNumbers[] = $context->stepNumber;

                return $next($context);
            }
        },
    ]);

    $driver = makeMiddlewareMockDriver([
        new TextResponse(
            text: 'calling tool',
            usage: new Usage(10, 5),
            finishReason: FinishReason::ToolCalls,
            toolCalls: [new ToolCall('tc-1', 'echo', ['input' => 'hi'])],
        ),
        new TextResponse(
            text: 'done',
            usage: new Usage(10, 5),
            finishReason: FinishReason::Stop,
        ),
    ]);

    $tool = makeMiddlewareEchoTool();
    $registry = new ToolRegistry([$tool]);
    $toolExecutor = new ToolExecutor($registry);
    $dispatcher = makeMiddlewareExecutorDispatcher();

    $executor = new AgentExecutor($driver, $toolExecutor, $dispatcher, app(MiddlewareStack::class));
    $executor->execute(makeMiddlewareExecutorRequest(), maxSteps: 10, parallelToolCalls: false);

    expect($stepNumbers)->toBe([1, 2]);

    config()->set('atlas.middleware.step', []);
});

it('step middleware receives accumulated usage', function () {
    $receivedUsage = null;

    config()->set('atlas.middleware.step', [
        new class($receivedUsage)
        {
            public function __construct(private ?Usage &$receivedUsage) {}

            public function handle(StepContext $context, Closure $next)
            {
                // Capture usage on the second step
                if ($context->stepNumber === 2) {
                    $this->receivedUsage = $context->accumulatedUsage;
                }

                return $next($context);
            }
        },
    ]);

    $driver = makeMiddlewareMockDriver([
        new TextResponse(
            text: 'calling tool',
            usage: new Usage(100, 50),
            finishReason: FinishReason::ToolCalls,
            toolCalls: [new ToolCall('tc-1', 'echo', ['input' => 'hi'])],
        ),
        new TextResponse(
            text: 'done',
            usage: new Usage(20, 10),
            finishReason: FinishReason::Stop,
        ),
    ]);

    $registry = new ToolRegistry([makeMiddlewareEchoTool()]);
    $executor = new AgentExecutor($driver, new ToolExecutor($registry), makeMiddlewareExecutorDispatcher(), app(MiddlewareStack::class));
    $executor->execute(makeMiddlewareExecutorRequest(), maxSteps: 10, parallelToolCalls: false);

    expect($receivedUsage)->not->toBeNull();
    expect($receivedUsage->inputTokens)->toBe(100);
    expect($receivedUsage->outputTokens)->toBe(50);

    config()->set('atlas.middleware.step', []);
});

it('runs tool middleware on each tool call', function () {
    $toolNames = [];

    config()->set('atlas.middleware.tool', [
        new class($toolNames)
        {
            public function __construct(private array &$toolNames) {}

            public function handle(ToolContext $context, Closure $next)
            {
                $this->toolNames[] = $context->toolCall->name;

                return $next($context);
            }
        },
    ]);

    $driver = makeMiddlewareMockDriver([
        new TextResponse(
            text: 'calling tool',
            usage: new Usage(10, 5),
            finishReason: FinishReason::ToolCalls,
            toolCalls: [new ToolCall('tc-1', 'echo', ['input' => 'hi'])],
        ),
        new TextResponse(
            text: 'done',
            usage: new Usage(10, 5),
            finishReason: FinishReason::Stop,
        ),
    ]);

    $registry = new ToolRegistry([makeMiddlewareEchoTool()]);
    $executor = new AgentExecutor($driver, new ToolExecutor($registry), makeMiddlewareExecutorDispatcher(), app(MiddlewareStack::class));
    $executor->execute(makeMiddlewareExecutorRequest(), maxSteps: 10, parallelToolCalls: false);

    expect($toolNames)->toBe(['echo']);

    config()->set('atlas.middleware.tool', []);
});

it('runs tool middleware in sequential path with single tool (parallel flag true)', function () {
    $toolNames = [];

    config()->set('atlas.middleware.tool', [
        new class($toolNames)
        {
            public function __construct(private array &$toolNames) {}

            public function handle(ToolContext $context, Closure $next)
            {
                $this->toolNames[] = $context->toolCall->name;

                return $next($context);
            }
        },
    ]);

    $driver = makeMiddlewareMockDriver([
        new TextResponse(
            text: 'calling tool',
            usage: new Usage(10, 5),
            finishReason: FinishReason::ToolCalls,
            toolCalls: [new ToolCall('tc-1', 'echo', ['input' => 'hi'])],
        ),
        new TextResponse(
            text: 'done',
            usage: new Usage(10, 5),
            finishReason: FinishReason::Stop,
        ),
    ]);

    $registry = new ToolRegistry([makeMiddlewareEchoTool()]);
    $executor = new AgentExecutor($driver, new ToolExecutor($registry), makeMiddlewareExecutorDispatcher(), app(MiddlewareStack::class));
    // parallelToolCalls: true with single tool falls back to sequential
    $executor->execute(makeMiddlewareExecutorRequest(), maxSteps: 10, parallelToolCalls: true);

    expect($toolNames)->toBe(['echo']);

    config()->set('atlas.middleware.tool', []);
});

it('works without middleware stack (backward compat)', function () {
    $driver = makeMiddlewareMockDriver([
        new TextResponse(
            text: 'ok',
            usage: new Usage(10, 5),
            finishReason: FinishReason::Stop,
        ),
    ]);

    $registry = new ToolRegistry([]);
    $executor = new AgentExecutor($driver, new ToolExecutor($registry), makeMiddlewareExecutorDispatcher());

    $result = $executor->execute(makeMiddlewareExecutorRequest(), maxSteps: 10);

    expect($result->text)->toBe('ok');
});

it('calls driver directly when middleware stack exists but step middleware config is empty', function () {
    config()->set('atlas.middleware.step', []);

    $driver = makeMiddlewareMockDriver([
        new TextResponse(
            text: 'direct',
            usage: new Usage(10, 5),
            finishReason: FinishReason::Stop,
        ),
    ]);

    $registry = new ToolRegistry([]);
    $executor = new AgentExecutor($driver, new ToolExecutor($registry), makeMiddlewareExecutorDispatcher(), app(MiddlewareStack::class));

    $result = $executor->execute(makeMiddlewareExecutorRequest(), maxSteps: 10);

    expect($result->text)->toBe('direct');
    expect($result->totalSteps())->toBe(1);

    config()->set('atlas.middleware.step', []);
});

it('calls tool executor directly when middleware stack exists but tool middleware config is empty', function () {
    config()->set('atlas.middleware.step', []);
    config()->set('atlas.middleware.tool', []);

    $driver = makeMiddlewareMockDriver([
        new TextResponse(
            text: 'calling tool',
            usage: new Usage(10, 5),
            finishReason: FinishReason::ToolCalls,
            toolCalls: [new ToolCall('tc-1', 'echo', ['input' => 'hi'])],
        ),
        new TextResponse(
            text: 'done',
            usage: new Usage(10, 5),
            finishReason: FinishReason::Stop,
        ),
    ]);

    $registry = new ToolRegistry([makeMiddlewareEchoTool()]);
    $executor = new AgentExecutor($driver, new ToolExecutor($registry), makeMiddlewareExecutorDispatcher(), app(MiddlewareStack::class));

    $result = $executor->execute(makeMiddlewareExecutorRequest(), maxSteps: 10, parallelToolCalls: false);

    expect($result->text)->toBe('done');
    expect($result->totalSteps())->toBe(2);
    expect($result->totalToolCalls())->toBe(1);
    expect($result->steps[0]->toolResults[0]->content)->toBe('hi');

    config()->set('atlas.middleware.step', []);
    config()->set('atlas.middleware.tool', []);
});
