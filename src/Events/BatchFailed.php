<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Persistence\Models\BatchJob;

/**
 * Fired when a tracked batch job reaches a failed, expired, or cancelled state.
 */
class BatchFailed
{
    public function __construct(public readonly BatchJob $job) {}
}
