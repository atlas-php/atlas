<?php

declare(strict_types=1);

use Atlasphp\Atlas\Middleware\Contracts\AgentMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\AudioMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\EmbedMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\ImageMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\ProviderMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\StepMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\TextMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\ToolMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\VideoMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\VoiceHttpMiddleware;
use Atlasphp\Atlas\Middleware\Contracts\VoiceMiddleware;
use Atlasphp\Atlas\Middleware\MiddlewareResolver;

it('routes agent middleware to agent layer', function () {
    $mw = new class implements AgentMiddleware
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);

    expect($resolver->forLayer('agent'))->toHaveCount(1);
    expect($resolver->forLayer('step'))->toBeEmpty();
    expect($resolver->forLayer('tool'))->toBeEmpty();
    expect($resolver->forProvider('text'))->toBeEmpty();
});

it('routes step middleware to step layer', function () {
    $mw = new class implements StepMiddleware
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);

    expect($resolver->forLayer('step'))->toHaveCount(1);
    expect($resolver->forLayer('agent'))->toBeEmpty();
});

it('routes tool middleware to tool layer', function () {
    $mw = new class implements ToolMiddleware
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);

    expect($resolver->forLayer('tool'))->toHaveCount(1);
    expect($resolver->forLayer('agent'))->toBeEmpty();
});

it('routes provider middleware to all provider methods', function () {
    $mw = new class implements ProviderMiddleware
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);

    expect($resolver->forProvider('text'))->toHaveCount(1);
    expect($resolver->forProvider('image'))->toHaveCount(1);
    expect($resolver->forProvider('audio'))->toHaveCount(1);
    expect($resolver->forProvider('video'))->toHaveCount(1);
    expect($resolver->forProvider('voice'))->toHaveCount(1);
    expect($resolver->forProvider('embed'))->toHaveCount(1);
    expect($resolver->forProvider('stream'))->toHaveCount(1);
    expect($resolver->forProvider('structured'))->toHaveCount(1);
});

it('routes text middleware only to text methods', function () {
    $mw = new class implements TextMiddleware
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);

    expect($resolver->forProvider('text'))->toHaveCount(1);
    expect($resolver->forProvider('stream'))->toHaveCount(1);
    expect($resolver->forProvider('structured'))->toHaveCount(1);
    expect($resolver->forProvider('image'))->toBeEmpty();
    expect($resolver->forProvider('audio'))->toBeEmpty();
    expect($resolver->forProvider('voice'))->toBeEmpty();
});

it('routes image middleware only to image methods', function () {
    $mw = new class implements ImageMiddleware
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);

    expect($resolver->forProvider('image'))->toHaveCount(1);
    expect($resolver->forProvider('imageToText'))->toHaveCount(1);
    expect($resolver->forProvider('text'))->toBeEmpty();
    expect($resolver->forProvider('audio'))->toBeEmpty();
});

it('routes audio middleware only to audio methods', function () {
    $mw = new class implements AudioMiddleware
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);

    expect($resolver->forProvider('audio'))->toHaveCount(1);
    expect($resolver->forProvider('audioToText'))->toHaveCount(1);
    expect($resolver->forProvider('text'))->toBeEmpty();
    expect($resolver->forProvider('image'))->toBeEmpty();
});

it('routes video middleware only to video methods', function () {
    $mw = new class implements VideoMiddleware
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);

    expect($resolver->forProvider('video'))->toHaveCount(1);
    expect($resolver->forProvider('videoToText'))->toHaveCount(1);
    expect($resolver->forProvider('text'))->toBeEmpty();
});

it('routes voice middleware only to voice method', function () {
    $mw = new class implements VoiceMiddleware
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);

    expect($resolver->forProvider('voice'))->toHaveCount(1);
    expect($resolver->forProvider('text'))->toBeEmpty();
    expect($resolver->forProvider('image'))->toBeEmpty();
});

it('routes embed middleware only to embed methods', function () {
    $mw = new class implements EmbedMiddleware
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);

    expect($resolver->forProvider('embed'))->toHaveCount(1);
    expect($resolver->forProvider('moderate'))->toHaveCount(1);
    expect($resolver->forProvider('rerank'))->toHaveCount(1);
    expect($resolver->forProvider('text'))->toBeEmpty();
});

it('routes voice http middleware to voice http collection', function () {
    $mw = new class implements VoiceHttpMiddleware
    {
        public function handle($request, Closure $next): mixed
        {
            return $next($request);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);

    expect($resolver->forVoiceHttp())->toHaveCount(1);
    expect($resolver->forLayer('agent'))->toBeEmpty();
    expect($resolver->forProvider('voice'))->toBeEmpty();
});

it('handles multi-modality middleware', function () {
    $mw = new class implements AudioMiddleware, ImageMiddleware
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);

    expect($resolver->forProvider('image'))->toHaveCount(1);
    expect($resolver->forProvider('imageToText'))->toHaveCount(1);
    expect($resolver->forProvider('audio'))->toHaveCount(1);
    expect($resolver->forProvider('audioToText'))->toHaveCount(1);
    expect($resolver->forProvider('text'))->toBeEmpty();
    expect($resolver->forProvider('video'))->toBeEmpty();
});

it('treats closures as all-modality provider middleware', function () {
    $closure = function ($ctx, Closure $next) {
        return $next($ctx);
    };

    $resolver = new MiddlewareResolver(app(), [$closure]);

    expect($resolver->forProvider('text'))->toHaveCount(1);
    expect($resolver->forProvider('image'))->toHaveCount(1);
    expect($resolver->forLayer('agent'))->toBeEmpty();
});

it('returns empty arrays for empty middleware list', function () {
    $resolver = new MiddlewareResolver(app(), []);

    expect($resolver->forLayer('agent'))->toBeEmpty();
    expect($resolver->forLayer('step'))->toBeEmpty();
    expect($resolver->forLayer('tool'))->toBeEmpty();
    expect($resolver->forProvider('text'))->toBeEmpty();
    expect($resolver->forVoiceHttp())->toBeEmpty();
});

it('resolves class strings from container', function () {
    app()->bind('test.agent.mw', function () {
        return new class implements AgentMiddleware
        {
            public function handle($ctx, Closure $next): mixed
            {
                return $next($ctx);
            }
        };
    });

    $resolver = new MiddlewareResolver(app(), ['test.agent.mw']);

    expect($resolver->forLayer('agent'))->toHaveCount(1);
});

it('combines direct provider and modality middleware', function () {
    $global = new class implements ProviderMiddleware
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    $imageOnly = new class implements ImageMiddleware
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$global, $imageOnly]);

    expect($resolver->forProvider('image'))->toHaveCount(2);
    expect($resolver->forProvider('text'))->toHaveCount(1);
});

it('provides complete middleware info via all()', function () {
    $agentMw = new class implements AgentMiddleware
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    $imageMw = new class implements ImageMiddleware
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$agentMw, $imageMw]);
    $all = $resolver->all();

    expect($all['agent'])->toHaveCount(1);
    expect($all['provider'])->toHaveCount(1);
    expect($all['provider'][0]['modalities'])->toBe(['image', 'imageToText']);
    expect($all['step'])->toBeEmpty();
    expect($all['tool'])->toBeEmpty();
    expect($all['voice_http'])->toBeEmpty();
});

it('caches resolution across multiple calls', function () {
    $mw = new class implements AgentMiddleware
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);

    $first = $resolver->forLayer('agent');
    $second = $resolver->forLayer('agent');

    expect($first)->toBe($second);
});

it('shows closure as all-modality in all() output', function () {
    $closure = function ($ctx, Closure $next) {
        return $next($ctx);
    };

    $resolver = new MiddlewareResolver(app(), [$closure]);
    $all = $resolver->all();

    expect($all['provider'])->toHaveCount(1);
    expect($all['provider'][0]['class'])->toBe('Closure');
    expect($all['provider'][0]['modalities'])->toBe(['*']);
    expect($all['agent'])->toBeEmpty();
});

it('shows voice http middleware in all() output', function () {
    $mw = new class implements VoiceHttpMiddleware
    {
        public function handle($request, Closure $next): mixed
        {
            return $next($request);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);
    $all = $resolver->all();

    expect($all['voice_http'])->toHaveCount(1);
    expect($all['voice_http'][0]['modalities'])->toBe([]);
    expect($all['provider'])->toBeEmpty();
});

it('resolves class-string middleware in all() output', function () {
    app()->bind('test.provider.mw', function () {
        return new class implements ProviderMiddleware
        {
            public function handle($ctx, Closure $next): mixed
            {
                return $next($ctx);
            }
        };
    });

    $resolver = new MiddlewareResolver(app(), ['test.provider.mw']);
    $all = $resolver->all();

    expect($all['provider'])->toHaveCount(1);
    expect($all['provider'][0]['class'])->toBe('test.provider.mw');
    expect($all['provider'][0]['modalities'])->toBe(['*']);
});

it('returns only global provider middleware for unknown method', function () {
    $global = new class implements ProviderMiddleware
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    $imageMw = new class implements ImageMiddleware
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$global, $imageMw]);

    // An unknown method should only match global ProviderMiddleware
    expect($resolver->forProvider('unknown'))->toHaveCount(1);
    expect($resolver->forProvider('unknown')[0])->toBe($global);
});

it('returns empty array for non-existent layer', function () {
    $mw = new class implements AgentMiddleware
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);

    expect($resolver->forLayer('nonexistent'))->toBeEmpty();
});

it('silently ignores non-object non-string entries like arrays', function () {
    $resolver = new MiddlewareResolver(app(), [
        [],                    // empty array
        ['agent' => []],       // old keyed format
        null,                  // null
        42,                    // integer
        true,                  // boolean
    ]);

    expect($resolver->forLayer('agent'))->toBeEmpty();
    expect($resolver->forLayer('step'))->toBeEmpty();
    expect($resolver->forProvider('text'))->toBeEmpty();
    expect($resolver->forVoiceHttp())->toBeEmpty();
    expect($resolver->all())->each->toBeArray();
});

it('silently ignores middleware with no recognized interface', function () {
    $mw = new class
    {
        public function handle($ctx, Closure $next): mixed
        {
            return $next($ctx);
        }
    };

    $resolver = new MiddlewareResolver(app(), [$mw]);

    expect($resolver->forLayer('agent'))->toBeEmpty();
    expect($resolver->forLayer('step'))->toBeEmpty();
    expect($resolver->forLayer('tool'))->toBeEmpty();
    expect($resolver->forProvider('text'))->toBeEmpty();
    expect($resolver->forVoiceHttp())->toBeEmpty();
});
