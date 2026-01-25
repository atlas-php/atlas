<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Contracts;

use Closure;

/**
 * Contract for pipeline handlers.
 *
 * Defines the interface for classes that can be used as handlers in the pipeline system.
 * Each handler receives data and a closure to pass control to the next handler in the chain.
 */
interface PipelineContract
{
    /**
     * Handle the pipeline data.
     *
     * @param  mixed  $data  The data being passed through the pipeline.
     * @param  Closure  $next  The next handler in the pipeline chain.
     * @return mixed The processed data.
     */
    public function handle(mixed $data, Closure $next): mixed;
}
