<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Exceptions;

/**
 * Thrown when the executor exceeds the configured maximum number of steps.
 *
 * This typically indicates a circular tool call pattern where the model
 * repeatedly invokes tools without reaching a final response.
 */
class MaxStepsExceededException extends AtlasException
{
    public function __construct(
        public readonly int $limit,
        public readonly int $current,
    ) {
        parent::__construct(
            "Agent executor exceeded the maximum of {$limit} steps (currently at step {$current}). "
            .'This may indicate a circular tool call pattern. Consider increasing maxSteps or reviewing tool behavior.'
        );
    }
}
