<?php

declare(strict_types=1);

use Atlasphp\Atlas\Middleware\MiddlewareStack;
use Atlasphp\Atlas\Middleware\ProviderContext;
use Atlasphp\Atlas\Requests\TextRequest;

it('calls destination directly with empty middleware', function () {
    $stack = app(MiddlewareStack::class);
    $called = false;

    $stack->run('context', [], function ($ctx) use (&$called) {
        $called = true;

        return 'result';
    });

    expect($called)->toBeTrue();
});

it('runs single middleware before and after', function () {
    $stack = app(MiddlewareStack::class);
    $order = [];

    $middleware = new class($order)
    {
        public function __construct(private array &$order) {}

        public function handle($context, Closure $next)
        {
            $this->order[] = 'before';
            $result = $next($context);
            $this->order[] = 'after';

            return $result;
        }
    };

    $stack->run('context', [$middleware], function ($ctx) use (&$order) {
        $order[] = 'destination';

        return 'result';
    });

    expect($order)->toBe(['before', 'destination', 'after']);
});

it('runs multiple middleware in onion order', function () {
    $stack = app(MiddlewareStack::class);
    $order = [];

    $makeMiddleware = function (string $name) use (&$order) {
        return new class($name, $order)
        {
            public function __construct(private string $name, private array &$order) {}

            public function handle($context, Closure $next)
            {
                $this->order[] = "{$this->name}:before";
                $result = $next($context);
                $this->order[] = "{$this->name}:after";

                return $result;
            }
        };
    };

    $stack->run('context', [$makeMiddleware('outer'), $makeMiddleware('inner')], function ($ctx) use (&$order) {
        $order[] = 'destination';

        return 'result';
    });

    expect($order)->toBe(['outer:before', 'inner:before', 'destination', 'inner:after', 'outer:after']);
});

it('short-circuits when middleware omits next', function () {
    $stack = app(MiddlewareStack::class);
    $destinationCalled = false;

    $middleware = new class
    {
        public function handle($context, Closure $next)
        {
            return 'short-circuited';
        }
    };

    $result = $stack->run('context', [$middleware], function ($ctx) use (&$destinationCalled) {
        $destinationCalled = true;

        return 'destination';
    });

    expect($destinationCalled)->toBeFalse();
    expect($result)->toBe('short-circuited');
});

it('propagates exceptions from middleware', function () {
    $stack = app(MiddlewareStack::class);

    $middleware = new class
    {
        public function handle($context, Closure $next)
        {
            throw new RuntimeException('middleware error');
        }
    };

    $stack->run('context', [$middleware], fn ($ctx) => 'result');
})->throws(RuntimeException::class, 'middleware error');

it('resolves class string middleware from container', function () {
    app()->bind('test.middleware', function () {
        return new class
        {
            public function handle($context, Closure $next)
            {
                return 'resolved:'.$next($context);
            }
        };
    });

    $stack = app(MiddlewareStack::class);

    $result = $stack->run('context', ['test.middleware'], fn ($ctx) => 'ok');

    expect($result)->toBe('resolved:ok');
});

it('accepts closure middleware', function () {
    $stack = app(MiddlewareStack::class);

    $result = $stack->run('context', [
        function ($context, Closure $next) {
            return 'closure:'.$next($context);
        },
    ], fn ($ctx) => 'ok');

    expect($result)->toBe('closure:ok');
});

it('passes context through to destination', function () {
    $stack = app(MiddlewareStack::class);

    $context = new ProviderContext(
        provider: 'openai',
        model: 'gpt-4o',
        method: 'text',
        request: new TextRequest(
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
        ),
    );

    $received = null;

    $stack->run($context, [], function ($ctx) use (&$received) {
        $received = $ctx;

        return 'ok';
    });

    expect($received)->toBe($context);
    expect($received->provider)->toBe('openai');
});
