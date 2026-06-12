<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Events;

use Atlasphp\Atlas\Persistence\Models\BatchGroup;

/**
 * Fired when every job in a batch group has reached a terminal state.
 */
class BatchGroupCompleted
{
    public function __construct(public readonly BatchGroup $group) {}
}
