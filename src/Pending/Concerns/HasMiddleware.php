<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending\Concerns;

use Closure;
use InvalidArgumentException;

/**
 * Adds middleware support to Pending request builders.
 *
 * Middleware set here is copied to the actual request object when buildRequest() is called,
 * and then merged with global config middleware by Driver::dispatch().
 */
trait HasMiddleware
{
    /** @var array<int, mixed> */
    protected array $middleware = [];

    /**
     * @param  array<int, mixed>  $middleware
     */
    public function withMiddleware(array $middleware): static
    {
        $this->middleware = array_merge($this->middleware, $middleware);

        return $this;
    }

    /**
     * Flatten middleware to class strings for queue serialization.
     *
     * Closures cannot cross the queue boundary, so fail fast at dispatch with a
     * clear message rather than serialize to the unusable string "Closure" and
     * crash the worker with a cryptic container error on rehydration.
     *
     * @return array<int, string>
     */
    protected function serializeMiddleware(): array
    {
        return array_map(function (mixed $m): string {
            if ($m instanceof Closure) {
                throw new InvalidArgumentException(
                    'Closure middleware cannot be queued. Use a class-based middleware for queued requests.'
                );
            }

            return is_string($m) ? $m : $m::class;
        }, $this->middleware);
    }
}
