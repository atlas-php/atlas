<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Persistence\Models\BatchJob;

/**
 * Fired when a tracked batch job has been submitted to the provider.
 */
class BatchSubmitted
{
    public function __construct(public readonly BatchJob $job) {}
}
