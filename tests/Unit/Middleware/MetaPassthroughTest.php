<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Http\HttpClient;
use Atlasphp\Atlas\Middleware\MiddlewareStack;
use Atlasphp\Atlas\Middleware\ProviderContext;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\Handlers\TextHandler;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;

function makeMetaTextRequest(array $meta = [], array $middleware = []): TextRequest
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
        meta: $meta,
    );
}

function makeMetaTestDriver(?MiddlewareStack $stack = null): Driver
{
    $config = ProviderConfig::fromArray(['api_key' => 'test', 'url' => 'https://api.test.com/v1']);
    $http = app(HttpClient::class);

    return new class($config, $http, $stack) extends Driver
    {
        public function name(): string
        {
            return 'test';
        }

        public function capabilities(): ProviderCapabilities
        {
            return new ProviderCapabilities(text: true, stream: true, structured: true);
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

it('meta from request reaches ProviderContext in middleware', function () {
    $receivedMeta = null;

    $middleware = new class($receivedMeta)
    {
        public function __construct(private ?array &$receivedMeta) {}

        public function handle(ProviderContext $context, Closure $next)
        {
            $this->receivedMeta = $context->meta;

            return $next($context);
        }
    };

    $driver = makeMetaTestDriver(app(MiddlewareStack::class));
    $request = makeMetaTextRequest(
        meta: ['user_id' => 123, 'session' => 'abc'],
        middleware: [$middleware],
    );

    $driver->text($request);

    expect($receivedMeta)->toBe(['user_id' => 123, 'session' => 'abc']);
});

it('meta flows through text dispatch', function () {
    $receivedMeta = null;

    config()->set('atlas.middleware.provider', [
        new class($receivedMeta)
        {
            public function __construct(private ?array &$receivedMeta) {}

            public function handle(ProviderContext $context, Closure $next)
            {
                $this->receivedMeta = $context->meta;

                return $next($context);
            }
        },
    ]);

    $driver = makeMetaTestDriver(app(MiddlewareStack::class));
    $driver->text(makeMetaTextRequest(meta: ['key' => 'value']));

    expect($receivedMeta)->toBe(['key' => 'value']);

    config()->set('atlas.middleware.provider', []);
});

it('meta flows through stream dispatch', function () {
    $receivedMethod = null;
    $receivedMeta = null;

    config()->set('atlas.middleware.provider', [
        new class($receivedMethod, $receivedMeta)
        {
            public function __construct(private ?string &$receivedMethod, private ?array &$receivedMeta) {}

            public function handle(ProviderContext $context, Closure $next)
            {
                $this->receivedMethod = $context->method;
                $this->receivedMeta = $context->meta;

                return $next($context);
            }
        },
    ]);

    $driver = makeMetaTestDriver(app(MiddlewareStack::class));
    $driver->stream(makeMetaTextRequest(meta: ['stream_id' => 'xyz']));

    expect($receivedMethod)->toBe('stream');
    expect($receivedMeta)->toBe(['stream_id' => 'xyz']);

    config()->set('atlas.middleware.provider', []);
});

it('meta defaults to empty array when not set', function () {
    $receivedMeta = null;

    $middleware = new class($receivedMeta)
    {
        public function __construct(private ?array &$receivedMeta) {}

        public function handle(ProviderContext $context, Closure $next)
        {
            $this->receivedMeta = $context->meta;

            return $next($context);
        }
    };

    $driver = makeMetaTestDriver(app(MiddlewareStack::class));
    $driver->text(makeMetaTextRequest(meta: [], middleware: [$middleware]));

    expect($receivedMeta)->toBe([]);
});

it('middleware can mutate meta for downstream middleware', function () {
    $finalMeta = null;

    $firstMiddleware = new class
    {
        public function handle(ProviderContext $context, Closure $next)
        {
            $context->meta['added_by_first'] = true;

            return $next($context);
        }
    };

    $secondMiddleware = new class($finalMeta)
    {
        public function __construct(private ?array &$finalMeta) {}

        public function handle(ProviderContext $context, Closure $next)
        {
            $this->finalMeta = $context->meta;

            return $next($context);
        }
    };

    $driver = makeMetaTestDriver(app(MiddlewareStack::class));
    $request = makeMetaTextRequest(
        meta: ['original' => 'data'],
        middleware: [$firstMiddleware, $secondMiddleware],
    );

    $driver->text($request);

    expect($finalMeta)->toBe(['original' => 'data', 'added_by_first' => true]);
});

it('TextRequest withAppendedMessages preserves meta', function () {
    $request = makeMetaTextRequest(meta: ['user_id' => 42]);
    $appended = $request->withAppendedMessages([]);

    expect($appended->meta)->toBe(['user_id' => 42]);
});
