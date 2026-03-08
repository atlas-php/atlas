<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Tools\Events;

use Atlasphp\Atlas\Tools\Contracts\ToolContract;
use Atlasphp\Atlas\Tools\Support\ToolContext;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched before a tool begins execution.
 *
 * Fired after the tool's before_execute pipeline has processed.
 */
class ToolExecuting
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $params  The parameter values passed to the tool.
     */
    public function __construct(
        public readonly ToolContract $tool,
        public readonly array $params,
        public readonly ToolContext $context,
    ) {}
}
