<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Middleware\MiddlewareStack;
use Atlasphp\Atlas\Middleware\ProviderContext;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\Handlers\TextHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;

function makeDispatchTextRequest(array $middleware = []): TextRequest
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
        middleware: $middleware,
    );
}

function makeDispatchDriver(?MiddlewareStack $stack = null): Driver
{
    $config = ProviderConfig::fromArray(['api_key' => 'test', 'url' => 'https://api.openai.com/v1']);
    $http = app(HttpClient::class);

    return new class($config, $http, $stack) extends Driver
    {
        public function name(): string
        {
            return 'test';
        }

        public function capabilities(): ProviderCapabilities
        {
            return new ProviderCapabilities(text: true, stream: true, structured: true, image: true, audio: true, video: true, embed: true, moderate: true);
        }

        protected function textHandler(): TextHandler
        {
            return new class implements TextHandler
            {
                public function text(TextRequest $request): TextResponse
                {
                    return new TextResponse(text: 'ok', usage: new Usage(10, 5), finishReason: FinishReason::Stop);
                }

                public function stream(TextRequest $request): StreamResponse
                {
                    return new StreamResponse((function () {
                        yield from [];
                    })());
                }

                public function structured(TextRequest $request): StructuredResponse
                {
                    return new StructuredResponse(structured: [], usage: new Usage(10, 5), finishReason: FinishReason::Stop);
                }
            };
        }
    };
}

it('calls handler directly with no middleware stack', function () {
    $driver = makeDispatchDriver(null);

    $response = $driver->text(makeDispatchTextRequest());

    expect($response->text)->toBe('ok');
});

it('calls handler directly with empty middleware', function () {
    $driver = makeDispatchDriver(app(MiddlewareStack::class));

    $response = $driver->text(makeDispatchTextRequest());

    expect($response->text)->toBe('ok');
});

it('runs global provider middleware on text call', function () {
    $called = false;

    config()->set('atlas.middleware.provider', [
        new class($called)
        {
            public function __construct(private bool &$called) {}

            public function handle(ProviderContext $context, Closure $next)
            {
                $this->called = true;

                return $next($context);
            }
        },
    ]);

    $driver = makeDispatchDriver(app(MiddlewareStack::class));
    $driver->text(makeDispatchTextRequest());

    expect($called)->toBeTrue();

    config()->set('atlas.middleware.provider', []);
});

it('runs request-level middleware', function () {
    $called = false;

    $middleware = new class($called)
    {
        public function __construct(private bool &$called) {}

        public function handle(ProviderContext $context, Closure $next)
        {
            $this->called = true;

            return $next($context);
        }
    };

    $driver = makeDispatchDriver(app(MiddlewareStack::class));
    $driver->text(makeDispatchTextRequest([$middleware]));

    expect($called)->toBeTrue();
});

it('merges global and request middleware with global outermost', function () {
    $order = [];

    $globalMw = new class($order)
    {
        public function __construct(private array &$order) {}

        public function handle(ProviderContext $context, Closure $next)
        {
            $this->order[] = 'global';

            return $next($context);
        }
    };

    $requestMw = new class($order)
    {
        public function __construct(private array &$order) {}

        public function handle(ProviderContext $context, Closure $next)
        {
            $this->order[] = 'request';

            return $next($context);
        }
    };

    config()->set('atlas.middleware.provider', [$globalMw]);

    $driver = makeDispatchDriver(app(MiddlewareStack::class));
    $driver->text(makeDispatchTextRequest([$requestMw]));

    expect($order)->toBe(['global', 'request']);

    config()->set('atlas.middleware.provider', []);
});

it('provides correct ProviderContext to middleware', function () {
    $receivedContext = null;

    $middleware = new class($receivedContext)
    {
        public function __construct(private ?ProviderContext &$receivedContext) {}

        public function handle(ProviderContext $context, Closure $next)
        {
            $this->receivedContext = $context;

            return $next($context);
        }
    };

    $driver = makeDispatchDriver(app(MiddlewareStack::class));
    $driver->text(makeDispatchTextRequest([$middleware]));

    expect($receivedContext)->toBeInstanceOf(ProviderContext::class);
    expect($receivedContext->provider)->toBe('test');
    expect($receivedContext->model)->toBe('gpt-4o');
    expect($receivedContext->method)->toBe('text');
});

it('dispatch method is correct for each modality', function () {
    $methods = [];

    $middleware = new class($methods)
    {
        public function __construct(private array &$methods) {}

        public function handle(ProviderContext $context, Closure $next)
        {
            $this->methods[] = $context->method;

            return $next($context);
        }
    };

    config()->set('atlas.middleware.provider', [$middleware]);

    $driver = makeDispatchDriver(app(MiddlewareStack::class));

    $driver->text(makeDispatchTextRequest());
    $driver->stream(makeDispatchTextRequest());
    $driver->structured(makeDispatchTextRequest());

    expect($methods)->toBe(['text', 'stream', 'structured']);

    config()->set('atlas.middleware.provider', []);
});
