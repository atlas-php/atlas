<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pipelines;

use Atlasphp\Atlas\Contracts\PipelineContract;
use Closure;
use Illuminate\Contracts\Container\Container;

/**
 * Wrapper handler that conditionally executes another pipeline handler.
 *
 * If the condition returns true, the wrapped handler is executed.
 * Otherwise, the data is passed directly to the next handler in the pipeline.
 */
class ConditionalPipelineHandler implements PipelineContract
{
    /**
     * @param  class-string<PipelineContract>|PipelineContract  $handler  The handler to conditionally execute.
     * @param  Closure(mixed): bool  $condition  Condition that determines if handler should run.
     * @param  Container|null  $container  Container for resolving class handlers.
     */
    public function __construct(
        protected string|PipelineContract $handler,
        protected Closure $condition,
        protected ?Container $container = null,
    ) {}

    /**
     * Handle the pipeline data conditionally.
     *
     * Checks the condition against the data. If the condition returns true,
     * executes the wrapped handler. Otherwise, passes data to the next handler.
     */
    public function handle(mixed $data, Closure $next): mixed
    {
        // Check condition - if false, skip this handler
        if (! ($this->condition)($data)) {
            return $next($data);
        }

        // Resolve handler instance
        $instance = is_string($this->handler)
            ? $this->resolveHandler($this->handler)
            : $this->handler;

        // Execute the handler
        return $instance->handle($data, $next);
    }

    /**
     * Resolve a handler class to an instance.
     *
     * @param  class-string<PipelineContract>  $handlerClass
     *
     * @throws \RuntimeException If container is not set.
     * @throws \InvalidArgumentException If handler doesn't implement PipelineContract.
     */
    protected function resolveHandler(string $handlerClass): PipelineContract
    {
        if ($this->container === null) {
            throw new \RuntimeException(
                sprintf(
                    'Container is required to resolve conditional pipeline handler class %s. '
                    .'Ensure PipelineRegistry::setContainer() is called during application bootstrap.',
                    $handlerClass
                )
            );
        }

        $instance = $this->container->make($handlerClass);

        if (! $instance instanceof PipelineContract) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Conditional pipeline handler must implement %s, got %s.',
                    PipelineContract::class,
                    is_object($instance) ? get_class($instance) : gettype($instance)
                )
            );
        }

        return $instance;
    }
}
