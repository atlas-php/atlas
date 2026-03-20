<?php

declare(strict_types=1);

use Atlasphp\Atlas\Contracts\ProviderRegistryContract;
use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Enums\Provider;
use Atlasphp\Atlas\Exceptions\AtlasException;
use Atlasphp\Atlas\Exceptions\UnsupportedFeatureException;
use Atlasphp\Atlas\Messages\UserMessage;
use Atlasphp\Atlas\Pending\TextRequest;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Providers\Tools\WebSearch;
use Atlasphp\Atlas\Requests\TextRequest as TextRequestObject;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;
use Atlasphp\Atlas\Schema\Schema;
use Atlasphp\Atlas\Tools\Tool;

function createTextPending(
    ?Driver $driver = null,
    Provider|string $provider = 'openai',
): TextRequest {
    $driver ??= Mockery::mock(Driver::class);
    $registry = Mockery::mock(ProviderRegistryContract::class);
    $key = $provider instanceof Provider ? $provider->value : $provider;
    $registry->shouldReceive('resolve')->with($key)->andReturn($driver);

    return new TextRequest($provider, 'gpt-4o', $registry);
}

it('returns $this from all fluent methods', function () {
    $pending = createTextPending();

    expect($pending->instructions('test'))->toBe($pending);
    expect($pending->message('hello'))->toBe($pending);
    expect($pending->withMessages([]))->toBe($pending);
    expect($pending->withMaxTokens(100))->toBe($pending);
    expect($pending->withTemperature(0.5))->toBe($pending);
    expect($pending->withProviderOptions([]))->toBe($pending);
});

it('builds request with correct defaults', function () {
    $request = createTextPending()->buildRequest();

    expect($request)->toBeInstanceOf(TextRequestObject::class);
    expect($request->model)->toBe('gpt-4o');
    expect($request->instructions)->toBeNull();
    expect($request->message)->toBeNull();
    expect($request->messageMedia)->toBe([]);
    expect($request->messages)->toBe([]);
    expect($request->maxTokens)->toBeNull();
    expect($request->temperature)->toBeNull();
    expect($request->schema)->toBeNull();
    expect($request->tools)->toBe([]);
    expect($request->providerTools)->toBe([]);
    expect($request->providerOptions)->toBe([]);
});

it('builds request with fluent values', function () {
    $schema = new Schema('test', 'test', []);
    $request = createTextPending()
        ->instructions('Be helpful')
        ->message('Hello')
        ->withMaxTokens(500)
        ->withTemperature(0.7)
        ->withSchema($schema)
        ->withProviderOptions(['key' => 'val'])
        ->buildRequest();

    expect($request->instructions)->toBe('Be helpful');
    expect($request->message)->toBe('Hello');
    expect($request->maxTokens)->toBe(500);
    expect($request->temperature)->toBe(0.7);
    expect($request->schema)->toBe($schema);
    expect($request->providerOptions)->toBe(['key' => 'val']);
});

it('dispatches asText to driver', function () {
    $response = new TextResponse('Hello!', new Usage(10, 5), FinishReason::Stop);
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(text: true));
    $driver->shouldReceive('text')->once()->andReturn($response);

    $result = createTextPending($driver)->message('Hi')->asText();

    expect($result)->toBe($response);
});

it('dispatches asStream to driver', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(stream: true));
    $driver->shouldReceive('stream')->once()->andReturn(Mockery::mock(StreamResponse::class));

    createTextPending($driver)->asStream();
});

it('dispatches asStructured to driver', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(structured: true));
    $driver->shouldReceive('structured')->once()->andReturn(Mockery::mock(StructuredResponse::class));

    createTextPending($driver)->asStructured();
});

it('throws when capability is unsupported', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(text: false));
    $driver->shouldReceive('name')->andReturn('test');

    createTextPending($driver)->asText();
})->throws(UnsupportedFeatureException::class);

it('resolves Provider enum', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(text: true));
    $driver->shouldReceive('text')->once()->andReturn(new TextResponse('ok', new Usage(1, 1), FinishReason::Stop));

    createTextPending($driver, Provider::OpenAI)->asText();
});

it('normalizes messages in withMessages', function () {
    $request = createTextPending()
        ->withMessages([['role' => 'user', 'content' => 'hello']])
        ->buildRequest();

    expect($request->messages)->toHaveCount(1);
    expect($request->messages[0])->toBeInstanceOf(UserMessage::class);
});

// ─── Tool fluent methods ────────────────────────────────────────────────────

it('withTools returns $this for chaining', function () {
    $pending = createTextPending();

    expect($pending->withTools([]))->toBe($pending);
});

it('withProviderTools returns $this for chaining', function () {
    $pending = createTextPending();

    expect($pending->withProviderTools([]))->toBe($pending);
});

it('withMaxSteps returns $this for chaining', function () {
    $pending = createTextPending();

    expect($pending->withMaxSteps(10))->toBe($pending);
});

it('withParallelToolCalls returns $this for chaining', function () {
    $pending = createTextPending();

    expect($pending->withParallelToolCalls(false))->toBe($pending);
});

it('withTools includes tool definitions in built request', function () {
    $tool = new class extends Tool
    {
        public function name(): string
        {
            return 'test_tool';
        }

        public function description(): string
        {
            return 'A test tool.';
        }

        public function handle(array $args, array $context): mixed
        {
            return 'result';
        }
    };

    $request = createTextPending()
        ->withTools([$tool])
        ->buildRequest();

    expect($request->tools)->toHaveCount(1);
    expect($request->tools[0]->name)->toBe('test_tool');
});

it('withTools accumulates across multiple calls', function () {
    $tool1 = new class extends Tool
    {
        public function name(): string
        {
            return 'tool_one';
        }

        public function description(): string
        {
            return 'First.';
        }

        public function handle(array $args, array $context): mixed
        {
            return null;
        }
    };

    $tool2 = new class extends Tool
    {
        public function name(): string
        {
            return 'tool_two';
        }

        public function description(): string
        {
            return 'Second.';
        }

        public function handle(array $args, array $context): mixed
        {
            return null;
        }
    };

    $request = createTextPending()
        ->withTools([$tool1])
        ->withTools([$tool2])
        ->buildRequest();

    expect($request->tools)->toHaveCount(2);
});

it('withProviderTools includes provider tools in built request', function () {
    $request = createTextPending()
        ->withProviderTools([new WebSearch])
        ->buildRequest();

    expect($request->providerTools)->toHaveCount(1);
    expect($request->providerTools[0])->toBeInstanceOf(WebSearch::class);
});

it('hasTools is true when tools are set', function () {
    $tool = new class extends Tool
    {
        public function name(): string
        {
            return 'test';
        }

        public function description(): string
        {
            return 'Test.';
        }

        public function handle(array $args, array $context): mixed
        {
            return null;
        }
    };

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(text: true));
    $driver->shouldReceive('text')->once()->andReturn(
        new TextResponse('tool response', new Usage(10, 5), FinishReason::Stop)
    );

    // With tools, asText should route through executeWithTools
    // Since the mock driver returns Stop (no tool calls), the executor completes in 1 step
    $result = createTextPending($driver)
        ->withTools([$tool])
        ->message('test')
        ->asText();

    expect($result->text)->toBe('tool response');
    expect($result->steps)->toHaveCount(1);
});

it('hasTools is true when only providerTools are set', function () {
    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(text: true));
    $driver->shouldReceive('text')->once()->andReturn(
        new TextResponse('provider tool response', new Usage(10, 5), FinishReason::Stop)
    );

    $result = createTextPending($driver)
        ->withProviderTools([new WebSearch])
        ->message('test')
        ->asText();

    expect($result->text)->toBe('provider tool response');
});

it('throws AtlasException when streaming with tools', function () {
    $tool = new class extends Tool
    {
        public function name(): string
        {
            return 'test';
        }

        public function description(): string
        {
            return 'Test.';
        }

        public function handle(array $args, array $context): mixed
        {
            return null;
        }
    };

    createTextPending()->withTools([$tool])->asStream();
})->throws(AtlasException::class, 'Streaming with tools is not yet supported');

it('throws AtlasException when streaming with provider tools', function () {
    createTextPending()->withProviderTools([new WebSearch])->asStream();
})->throws(AtlasException::class, 'Streaming with tools is not yet supported');

it('resolves tool class strings from the container', function () {
    $toolClass = get_class(new class extends Tool
    {
        public function name(): string
        {
            return 'resolved_tool';
        }

        public function description(): string
        {
            return 'Resolved.';
        }

        public function handle(array $args, array $context): mixed
        {
            return null;
        }
    });

    app()->bind($toolClass, fn () => new $toolClass);

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(text: true));
    $driver->shouldReceive('text')->once()->andReturn(
        new TextResponse('ok', new Usage(1, 1), FinishReason::Stop)
    );

    $result = createTextPending($driver)
        ->withTools([$toolClass])
        ->asText();

    expect($result->text)->toBe('ok');
});

it('withMaxSteps passes through to executor', function () {
    $tool = new class extends Tool
    {
        public function name(): string
        {
            return 'echo';
        }

        public function description(): string
        {
            return 'Echo.';
        }

        public function handle(array $args, array $context): mixed
        {
            return 'done';
        }
    };

    $driver = Mockery::mock(Driver::class);
    $driver->shouldReceive('capabilities')->andReturn(new ProviderCapabilities(text: true));
    $driver->shouldReceive('text')->once()->andReturn(
        new TextResponse('ok', new Usage(1, 1), FinishReason::Stop)
    );

    // Just verify it doesn't blow up — maxSteps is passed to executor
    $result = createTextPending($driver)
        ->withTools([$tool])
        ->withMaxSteps(5)
        ->asText();

    expect($result->text)->toBe('ok');
});
