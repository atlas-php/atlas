<?php

declare(strict_types=1);

use Atlasphp\Atlas\Enums\FinishReason;
use Atlasphp\Atlas\Middleware\MiddlewareStack;
use Atlasphp\Atlas\Middleware\ProviderContext;
use Atlasphp\Atlas\Providers\Driver;
use Atlasphp\Atlas\Providers\Handlers\AudioHandler;
use Atlasphp\Atlas\Providers\Handlers\EmbedHandler;
use Atlasphp\Atlas\Providers\Handlers\ImageHandler;
use Atlasphp\Atlas\Providers\Handlers\ModerateHandler;
use Atlasphp\Atlas\Providers\Handlers\RerankHandler;
use Atlasphp\Atlas\Providers\Handlers\TextHandler;
use Atlasphp\Atlas\Providers\Handlers\VideoHandler;
use Atlasphp\Atlas\Providers\HttpClient;
use Atlasphp\Atlas\Providers\ProviderCapabilities;
use Atlasphp\Atlas\Providers\ProviderConfig;
use Atlasphp\Atlas\Requests\AudioRequest;
use Atlasphp\Atlas\Requests\EmbedRequest;
use Atlasphp\Atlas\Requests\ImageRequest;
use Atlasphp\Atlas\Requests\ModerateRequest;
use Atlasphp\Atlas\Requests\RerankRequest;
use Atlasphp\Atlas\Requests\TextRequest;
use Atlasphp\Atlas\Requests\VideoRequest;
use Atlasphp\Atlas\Responses\AudioResponse;
use Atlasphp\Atlas\Responses\EmbeddingsResponse;
use Atlasphp\Atlas\Responses\ImageResponse;
use Atlasphp\Atlas\Responses\ModerationResponse;
use Atlasphp\Atlas\Responses\RerankResponse;
use Atlasphp\Atlas\Responses\RerankResult;
use Atlasphp\Atlas\Responses\StreamResponse;
use Atlasphp\Atlas\Responses\StructuredResponse;
use Atlasphp\Atlas\Responses\TextResponse;
use Atlasphp\Atlas\Responses\Usage;
use Atlasphp\Atlas\Responses\VideoResponse;

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
            return new ProviderCapabilities(text: true, stream: true, structured: true, image: true, audio: true, video: true, embed: true, moderate: true, rerank: true);
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

        protected function imageHandler(): ImageHandler
        {
            return new class implements ImageHandler
            {
                public function image(ImageRequest $request): ImageResponse
                {
                    return new ImageResponse(url: 'https://test.com/image.png');
                }

                public function imageToText(ImageRequest $request): TextResponse
                {
                    return new TextResponse(text: 'a cat', usage: new Usage(10, 5), finishReason: FinishReason::Stop);
                }
            };
        }

        protected function audioHandler(): AudioHandler
        {
            return new class implements AudioHandler
            {
                public function audio(AudioRequest $request): AudioResponse
                {
                    return new AudioResponse(data: 'audio-data');
                }

                public function audioToText(AudioRequest $request): TextResponse
                {
                    return new TextResponse(text: 'hello', usage: new Usage(10, 5), finishReason: FinishReason::Stop);
                }
            };
        }

        protected function videoHandler(): VideoHandler
        {
            return new class implements VideoHandler
            {
                public function video(VideoRequest $request): VideoResponse
                {
                    return new VideoResponse(url: 'https://test.com/video.mp4');
                }

                public function videoToText(VideoRequest $request): TextResponse
                {
                    return new TextResponse(text: 'a sunset', usage: new Usage(10, 5), finishReason: FinishReason::Stop);
                }
            };
        }

        protected function embedHandler(): EmbedHandler
        {
            return new class implements EmbedHandler
            {
                public function embed(EmbedRequest $request): EmbeddingsResponse
                {
                    return new EmbeddingsResponse(embeddings: [[0.1, 0.2]], usage: new Usage(5, 0));
                }
            };
        }

        protected function moderateHandler(): ModerateHandler
        {
            return new class implements ModerateHandler
            {
                public function moderate(ModerateRequest $request): ModerationResponse
                {
                    return new ModerationResponse(flagged: false);
                }
            };
        }

        protected function rerankHandler(): RerankHandler
        {
            return new class implements RerankHandler
            {
                public function rerank(RerankRequest $request): RerankResponse
                {
                    return new RerankResponse(results: [new RerankResult(index: 0, score: 0.9, document: 'doc')]);
                }
            };
        }
    };
}

function makeDispatchImageRequest(array $middleware = []): ImageRequest
{
    return new ImageRequest(
        model: 'dall-e-3',
        instructions: 'A cat',
        media: [],
        size: null,
        quality: null,
        format: null,
        providerOptions: [],
        middleware: $middleware,
    );
}

function makeDispatchAudioRequest(array $middleware = []): AudioRequest
{
    return new AudioRequest(
        model: 'tts-1',
        instructions: 'Say hello',
        media: [],
        voice: null,
        speed: null,
        language: null,
        duration: null,
        format: null,
        voiceClone: null,
        providerOptions: [],
        middleware: $middleware,
    );
}

function makeDispatchVideoRequest(array $middleware = []): VideoRequest
{
    return new VideoRequest(
        model: 'sora',
        instructions: 'A sunset',
        media: [],
        duration: null,
        ratio: null,
        format: null,
        providerOptions: [],
        middleware: $middleware,
    );
}

function makeDispatchEmbedRequest(array $middleware = []): EmbedRequest
{
    return new EmbedRequest(
        model: 'text-embedding-3-small',
        input: 'test',
        providerOptions: [],
        middleware: $middleware,
    );
}

function makeDispatchModerateRequest(array $middleware = []): ModerateRequest
{
    return new ModerateRequest(
        input: 'test content',
        model: 'text-moderation-latest',
        providerOptions: [],
        middleware: $middleware,
    );
}

function makeDispatchRerankRequest(array $middleware = []): RerankRequest
{
    return new RerankRequest(
        model: 'rerank-v3',
        query: 'What is Laravel?',
        documents: ['Doc 1', 'Doc 2'],
        providerOptions: [],
        middleware: $middleware,
    );
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

// ─── Non-text modality dispatch method tests ───────────────────────────────────

it('dispatch method is correct for image modality', function () {
    $receivedMethod = null;

    config()->set('atlas.middleware.provider', [
        new class($receivedMethod)
        {
            public function __construct(private ?string &$receivedMethod) {}

            public function handle(ProviderContext $context, Closure $next)
            {
                $this->receivedMethod = $context->method;

                return $next($context);
            }
        },
    ]);

    $driver = makeDispatchDriver(app(MiddlewareStack::class));
    $driver->image(makeDispatchImageRequest());

    expect($receivedMethod)->toBe('image');

    config()->set('atlas.middleware.provider', []);
});

it('dispatch method is correct for audio modality', function () {
    $receivedMethod = null;

    config()->set('atlas.middleware.provider', [
        new class($receivedMethod)
        {
            public function __construct(private ?string &$receivedMethod) {}

            public function handle(ProviderContext $context, Closure $next)
            {
                $this->receivedMethod = $context->method;

                return $next($context);
            }
        },
    ]);

    $driver = makeDispatchDriver(app(MiddlewareStack::class));
    $driver->audio(makeDispatchAudioRequest());

    expect($receivedMethod)->toBe('audio');

    config()->set('atlas.middleware.provider', []);
});

it('dispatch method is correct for video modality', function () {
    $receivedMethod = null;

    config()->set('atlas.middleware.provider', [
        new class($receivedMethod)
        {
            public function __construct(private ?string &$receivedMethod) {}

            public function handle(ProviderContext $context, Closure $next)
            {
                $this->receivedMethod = $context->method;

                return $next($context);
            }
        },
    ]);

    $driver = makeDispatchDriver(app(MiddlewareStack::class));
    $driver->video(makeDispatchVideoRequest());

    expect($receivedMethod)->toBe('video');

    config()->set('atlas.middleware.provider', []);
});

it('dispatch method is correct for embed modality', function () {
    $receivedMethod = null;

    config()->set('atlas.middleware.provider', [
        new class($receivedMethod)
        {
            public function __construct(private ?string &$receivedMethod) {}

            public function handle(ProviderContext $context, Closure $next)
            {
                $this->receivedMethod = $context->method;

                return $next($context);
            }
        },
    ]);

    $driver = makeDispatchDriver(app(MiddlewareStack::class));
    $driver->embed(makeDispatchEmbedRequest());

    expect($receivedMethod)->toBe('embed');

    config()->set('atlas.middleware.provider', []);
});

it('dispatch method is correct for moderate modality', function () {
    $receivedMethod = null;

    config()->set('atlas.middleware.provider', [
        new class($receivedMethod)
        {
            public function __construct(private ?string &$receivedMethod) {}

            public function handle(ProviderContext $context, Closure $next)
            {
                $this->receivedMethod = $context->method;

                return $next($context);
            }
        },
    ]);

    $driver = makeDispatchDriver(app(MiddlewareStack::class));
    $driver->moderate(makeDispatchModerateRequest());

    expect($receivedMethod)->toBe('moderate');

    config()->set('atlas.middleware.provider', []);
});

it('dispatch method is correct for rerank modality', function () {
    $receivedMethod = null;

    config()->set('atlas.middleware.provider', [
        new class($receivedMethod)
        {
            public function __construct(private ?string &$receivedMethod) {}

            public function handle(ProviderContext $context, Closure $next)
            {
                $this->receivedMethod = $context->method;

                return $next($context);
            }
        },
    ]);

    $driver = makeDispatchDriver(app(MiddlewareStack::class));
    $driver->rerank(makeDispatchRerankRequest());

    expect($receivedMethod)->toBe('rerank');

    config()->set('atlas.middleware.provider', []);
});
