<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending\Concerns;

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
}
