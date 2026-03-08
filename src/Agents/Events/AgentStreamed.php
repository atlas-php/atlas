<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Agents\Events;

use Atlasphp\Atlas\Agents\Contracts\AgentContract;
use Atlasphp\Atlas\Agents\Support\AgentContext;
use Illuminate\Foundation\Events\Dispatchable;
use Prism\Prism\Streaming\Events\StreamEvent;

/**
 * Dispatched when an agent completes streaming.
 *
 * Fired after the stream has been fully consumed and after pipelines.
 * Contains the collected stream events.
 */
class AgentStreamed
{
    use Dispatchable;

    /**
     * @param  array<int, StreamEvent>  $events  The collected stream events.
     */
    public function __construct(
        public readonly AgentContract $agent,
        public readonly string $input,
        public readonly AgentContext $context,
        public readonly array $events,
    ) {}
}
