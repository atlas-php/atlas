<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Foundation\Services;

use Atlasphp\Atlas\Foundation\Contracts\PipelineContract;
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
            fn (Closure $next, string|PipelineContract $handler) => $this->createPipelineStep($handler, $next),
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
     * Create a pipeline step closure for a handler.
     *
     * @param  class-string<PipelineContract>|PipelineContract  $handler
     */
    protected function createPipelineStep(string|PipelineContract $handler, Closure $next): Closure
    {
        return function (mixed $passable) use ($handler, $next): mixed {
            $instance = is_string($handler)
                ? $this->container->make($handler)
                : $handler;

            return $instance->handle($passable, $next);
        };
    }
}
