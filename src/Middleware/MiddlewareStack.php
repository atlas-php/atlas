<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Middleware;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Pipeline\Pipeline;

/**
 * Resolves and runs middleware through Laravel's Pipeline.
 *
 * Middleware can be: an object with handle(), a class string (resolved from container),
 * or a Closure. Empty middleware arrays short-circuit directly to the destination.
 */
class MiddlewareStack
{
    public function __construct(
        protected readonly Container $container,
    ) {}

    /**
     * Run a context through middleware, then call the destination.
     *
     * @param  array<int, mixed>  $middleware
     */
    public function run(mixed $context, array $middleware, Closure $destination): mixed
    {
        if ($middleware === []) {
            return $destination($context);
        }

        return (new Pipeline($this->container))
            ->send($context)
            ->through($this->resolve($middleware))
            ->then($destination);
    }

    /**
     * Resolve middleware entries to callable instances.
     *
     * @param  array<int, mixed>  $middleware
     * @return array<int, mixed>
     */
    protected function resolve(array $middleware): array
    {
        return array_map(function (mixed $m): mixed {
            if (is_string($m)) {
                return $this->container->make($m);
            }

            return $m;
        }, $middleware);
    }
}
