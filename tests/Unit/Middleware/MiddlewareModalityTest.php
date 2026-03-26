<?php

declare(strict_types=1);

use Atlasphp\Atlas\Middleware\Contracts\AudioMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\EmbedMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\ImageMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\ProviderMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\TextMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\VideoMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\VoiceMiddleware;
use Atlasphp\Atlas\Middleware\MiddlewareResolver;
use Atlasphp\Atlas\Middleware\MiddlewareStack;
use Atlasphp\Atlas\Middleware\ProviderContext;
use Atlasphp\Atlas\Requests\TextRequest;

// ─── Text Modality ──────────────────────────────────────────────────────

it('text middleware receives provider context with text method', function () {
    $captured = null;

    $mw = new class($captured) implements TextMiddleware
    {
        public function __construct(private ?ProviderContext &$captured) {}

        public function handle(ProviderContext $context, Closure $next): mixed
        {
            $this->captured = $context;

            return $next($context);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);
    $stack = app(MiddlewareStack::class);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'gpt-4o',
        method: 'text',
        request: buildTextRequest(),
    );

    $middleware = $resolver->forProvider('text');
    $stack->run($context, $middleware, fn ($ctx) => 'done');

    expect($captured)->not->toBeNull();
    expect($captured->provider)->toBe('openai');
    expect($captured->model)->toBe('gpt-4o');
    expect($captured->method)->toBe('text');
});

it('text middleware runs on stream and structured methods', function () {
    $methods = [];

    $mw = new class($methods) implements TextMiddleware
    {
        public function __construct(private array &$methods) {}

        public function handle(ProviderContext $context, Closure $next): mixed
        {
            $this->methods[] = $context->method;

            return $next($context);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);
    $stack = app(MiddlewareStack::class);

    foreach (['text', 'stream', 'structured'] as $method) {
        $context = new ProviderContext(
            provider: 'openai',
            model: 'gpt-4o',
            method: $method,
            request: buildTextRequest(),
        );
        $stack->run($context, $resolver->forProvider($method), fn ($ctx) => 'done');
    }

    expect($methods)->toBe(['text', 'stream', 'structured']);
});

it('text middleware does not run on image method', function () {
    $called = false;

    $mw = new class($called) implements TextMiddleware
    {
        public function __construct(private bool &$called) {}

        public function handle(ProviderContext $context, Closure $next): mixed
        {
            $this->called = true;

            return $next($context);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);
    expect($resolver->forProvider('image'))->toBeEmpty();
    expect($called)->toBeFalse();
});

// ─── Image Modality ─────────────────────────────────────────────────────

it('image middleware receives context with image method', function () {
    $captured = null;

    $mw = new class($captured) implements ImageMiddleware
    {
        public function __construct(private ?ProviderContext &$captured) {}

        public function handle(ProviderContext $context, Closure $next): mixed
        {
            $this->captured = $context;

            return $next($context);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);
    $stack = app(MiddlewareStack::class);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'dall-e-3',
        method: 'image',
        request: buildTextRequest('dall-e-3'),
    );

    $stack->run($context, $resolver->forProvider('image'), fn ($ctx) => 'done');

    expect($captured)->not->toBeNull();
    expect($captured->method)->toBe('image');
    expect($captured->model)->toBe('dall-e-3');
});

it('image middleware runs on imageToText but not text', function () {
    $methods = [];

    $mw = new class($methods) implements ImageMiddleware
    {
        public function __construct(private array &$methods) {}

        public function handle(ProviderContext $context, Closure $next): mixed
        {
            $this->methods[] = $context->method;

            return $next($context);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);

    expect($resolver->forProvider('image'))->toHaveCount(1);
    expect($resolver->forProvider('imageToText'))->toHaveCount(1);
    expect($resolver->forProvider('text'))->toBeEmpty();
});

// ─── Audio Modality ─────────────────────────────────────────────────────

it('audio middleware runs on audio and audioToText', function () {
    $mw = new class implements AudioMiddleware
    {
        public function handle(ProviderContext $context, Closure $next): mixed
        {
            return $next($context);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);

    expect($resolver->forProvider('audio'))->toHaveCount(1);
    expect($resolver->forProvider('audioToText'))->toHaveCount(1);
    expect($resolver->forProvider('text'))->toBeEmpty();
    expect($resolver->forProvider('video'))->toBeEmpty();
});

// ─── Video Modality ─────────────────────────────────────────────────────

it('video middleware runs on video and videoToText', function () {
    $mw = new class implements VideoMiddleware
    {
        public function handle(ProviderContext $context, Closure $next): mixed
        {
            return $next($context);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);

    expect($resolver->forProvider('video'))->toHaveCount(1);
    expect($resolver->forProvider('videoToText'))->toHaveCount(1);
    expect($resolver->forProvider('image'))->toBeEmpty();
});

// ─── Voice Modality ─────────────────────────────────────────────────────

it('voice middleware runs only on voice method', function () {
    $mw = new class implements VoiceMiddleware
    {
        public function handle(ProviderContext $context, Closure $next): mixed
        {
            return $next($context);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);

    expect($resolver->forProvider('voice'))->toHaveCount(1);
    expect($resolver->forProvider('text'))->toBeEmpty();
    expect($resolver->forProvider('audio'))->toBeEmpty();
});

// ─── Embed Modality ─────────────────────────────────────────────────────

it('embed middleware runs on embed moderate and rerank', function () {
    $mw = new class implements EmbedMiddleware
    {
        public function handle(ProviderContext $context, Closure $next): mixed
        {
            return $next($context);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);

    expect($resolver->forProvider('embed'))->toHaveCount(1);
    expect($resolver->forProvider('moderate'))->toHaveCount(1);
    expect($resolver->forProvider('rerank'))->toHaveCount(1);
    expect($resolver->forProvider('text'))->toBeEmpty();
});

// ─── Global Provider ────────────────────────────────────────────────────

it('global provider middleware runs on every modality method', function () {
    $methods = [];

    $mw = new class($methods) implements ProviderMiddleware
    {
        public function __construct(private array &$methods) {}

        public function handle(ProviderContext $context, Closure $next): mixed
        {
            $this->methods[] = $context->method;

            return $next($context);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);
    $stack = app(MiddlewareStack::class);

    $allMethods = ['text', 'stream', 'structured', 'image', 'imageToText', 'audio', 'audioToText', 'video', 'videoToText', 'voice', 'embed', 'moderate', 'rerank'];

    foreach ($allMethods as $method) {
        $context = new ProviderContext(
            provider: 'openai',
            model: 'gpt-4o',
            method: $method,
            request: buildTextRequest(),
        );
        $stack->run($context, $resolver->forProvider($method), fn ($ctx) => 'done');
    }

    expect($methods)->toBe($allMethods);
});

// ─── Provider Context Access ────────────────────────────────────────────

it('provider middleware can read and modify context', function () {
    $mw = new class implements ProviderMiddleware
    {
        public function handle(ProviderContext $context, Closure $next): mixed
        {
            // Can read provider info
            expect($context->provider)->toBe('anthropic');
            expect($context->model)->toBe('claude-sonnet-4-20250514');
            expect($context->method)->toBe('text');

            // Can modify mutable properties
            $context->meta['modified'] = true;

            return $next($context);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);
    $stack = app(MiddlewareStack::class);

    $context = new ProviderContext(
        provider: 'anthropic',
        model: 'claude-sonnet-4-20250514',
        method: 'text',
        request: buildTextRequest('claude-sonnet-4-20250514'),
        meta: ['user_id' => 42],
    );

    $stack->run($context, $resolver->forProvider('text'), fn ($ctx) => $ctx);

    expect($context->meta['modified'])->toBeTrue();
    expect($context->meta['user_id'])->toBe(42);
});

// ─── Mixed Global + Modality ────────────────────────────────────────────

it('global and modality middleware both run on matching methods', function () {
    $order = [];

    $global = new class($order) implements ProviderMiddleware
    {
        public function __construct(private array &$order) {}

        public function handle(ProviderContext $context, Closure $next): mixed
        {
            $this->order[] = 'global';

            return $next($context);
        }
    };

    $imageOnly = new class($order) implements ImageMiddleware
    {
        public function __construct(private array &$order) {}

        public function handle(ProviderContext $context, Closure $next): mixed
        {
            $this->order[] = 'image';

            return $next($context);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$global, $imageOnly]);
    $stack = app(MiddlewareStack::class);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'dall-e-3',
        method: 'image',
        request: buildTextRequest('dall-e-3'),
    );

    $stack->run($context, $resolver->forProvider('image'), fn ($ctx) => 'done');

    expect($order)->toBe(['global', 'image']);
});

it('only global runs on methods without modality middleware', function () {
    $order = [];

    $global = new class($order) implements ProviderMiddleware
    {
        public function __construct(private array &$order) {}

        public function handle(ProviderContext $context, Closure $next): mixed
        {
            $this->order[] = 'global';

            return $next($context);
        }
    };

    $imageOnly = new class($order) implements ImageMiddleware
    {
        public function __construct(private array &$order) {}

        public function handle(ProviderContext $context, Closure $next): mixed
        {
            $this->order[] = 'image';

            return $next($context);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$global, $imageOnly]);
    $stack = app(MiddlewareStack::class);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'gpt-4o',
        method: 'text',
        request: buildTextRequest(),
    );

    $stack->run($context, $resolver->forProvider('text'), fn ($ctx) => 'done');

    expect($order)->toBe(['global']);
});

// ─── Helper ─────────────────────────────────────────────────────────────

function buildTextRequest(string $model = 'gpt-4o'): TextRequest
{
    return new TextRequest(
        model: $model,
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
