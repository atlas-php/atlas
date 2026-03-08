<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Events;

use Atlasphp\Atlas\Tools\Contracts\ToolContract;
use Atlasphp\Atlas\Tools\Support\ToolContext;
use Atlasphp\Atlas\Tools\Support\ToolResult;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after a tool completes execution.
 *
 * Fired after the tool's after_execute pipeline has processed.
 * Includes execution duration for performance monitoring.
 */
class ToolExecuted
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $params  The parameter values that were passed.
     * @param  float  $duration  Tool handle() execution duration in milliseconds (excludes pipeline overhead).
     */
    public function __construct(
        public readonly ToolContract $tool,
        public readonly array $params,
        public readonly ToolContext $context,
        public readonly ToolResult $result,
        public readonly float $duration,
    ) {}
}
