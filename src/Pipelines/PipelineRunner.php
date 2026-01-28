<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pipelines;

use Atlasphp\Atlas\Contracts\PipelineContract;
use Closure;
use Illuminate\Contracts\Container\Container;

/**
 * Executes pipeline handlers in priority order.
 *
 * Passes data through each handler with support for early termination
 * and optional destination handlers.
 */
class PipelineRunner
{
    public function __construct(
        protected PipelineRegistry $registry,
        protected Container $container,
    ) {}

    /**
     * Run a pipeline with the given data.
     *
     * @param  string  $name  The pipeline name.
     * @param  mixed  $data  The data to process.
     * @param  Closure|null  $destination  Optional final destination handler.
     * @return mixed The processed data.
     */
    public function run(string $name, mixed $data, ?Closure $destination = null): mixed
    {
        $handlers = $this->registry->get($name);

        if ($handlers === []) {
            return $destination !== null ? $destination($data) : $data;
        }

        $pipeline = array_reduce(
            array_reverse($handlers),
            fn (Closure $next, string|PipelineContract $handler) => $this->createPipelineStep($name, $handler, $next),
            $destination ?? fn (mixed $passable) => $passable,
        );

        return $pipeline($data);
    }

    /**
     * Run a pipeline only if it's active.
     *
     * @param  string  $name  The pipeline name.
     * @param  mixed  $data  The data to process.
     * @param  Closure|null  $destination  Optional final destination handler.
     * @return mixed The processed data (unchanged if pipeline is inactive).
     */
    public function runIfActive(string $name, mixed $data, ?Closure $destination = null): mixed
    {
        if (! $this->registry->active($name)) {
            return $destination !== null ? $destination($data) : $data;
        }

        return $this->run($name, $data, $destination);
    }

    /**
     * Run a pipeline with runtime handlers merged with global handlers.
     *
     * Runtime handlers are merged with global handlers and sorted by priority.
     * Global handlers run first (sorted by their registered priority), followed
     * by runtime handlers (in registration order, all with priority 0).
     *
     * @param  string  $name  The pipeline name.
     * @param  mixed  $data  The data to process.
     * @param  array<int, array{handler: class-string<PipelineContract>|PipelineContract, priority: int}>  $runtimeHandlers  Runtime handlers to merge.
     * @param  Closure|null  $destination  Optional final destination handler.
     * @return mixed The processed data.
     */
    public function runWithRuntime(
        string $name,
        mixed $data,
        array $runtimeHandlers = [],
        ?Closure $destination = null,
    ): mixed {
        if (! $this->registry->active($name)) {
            return $destination !== null ? $destination($data) : $data;
        }

        if ($runtimeHandlers === []) {
            return $this->run($name, $data, $destination);
        }

        // Merge global + runtime handlers, sort by priority (highest first)
        $allHandlers = [...$this->registry->getWithPriority($name), ...$runtimeHandlers];
        usort($allHandlers, fn (array $a, array $b) => $b['priority'] <=> $a['priority']);
        $handlers = array_column($allHandlers, 'handler');

        $pipeline = array_reduce(
            array_reverse($handlers),
            fn (Closure $next, string|PipelineContract $handler) => $this->createPipelineStep($name, $handler, $next),
            $destination ?? fn (mixed $passable) => $passable,
        );

        return $pipeline($data);
    }

    /**
     * Create a pipeline step closure for a handler.
     *
     * @param  string  $pipelineName  The pipeline name (for error messages).
     * @param  class-string<PipelineContract>|PipelineContract  $handler
     */
    protected function createPipelineStep(string $pipelineName, string|PipelineContract $handler, Closure $next): Closure
    {
        return function (mixed $passable) use ($pipelineName, $handler, $next): mixed {
            $instance = is_string($handler)
                ? $this->container->make($handler)
                : $handler;

            if (! $instance instanceof PipelineContract) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Pipeline handler for "%s" must implement %s, got %s.',
                        $pipelineName,
                        PipelineContract::class,
                        is_object($instance) ? get_class($instance) : gettype($instance)
                    )
                );
            }

            return $instance->handle($passable, $next);
        };
    }
}
