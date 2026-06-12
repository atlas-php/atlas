<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Persistence\Models\BatchJob;

/**
 * Fired when a tracked batch job completes and its results are hydrated.
 *
 * Hook this to write results back to your own models by their custom id.
 */
class BatchCompleted
{
    public function __construct(public readonly BatchJob $job) {}
}
