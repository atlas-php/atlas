<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Exceptions;

/**
 * Thrown when sub-agent delegation forms a cycle.
 *
 * Detected when an agent already present in the active delegation chain is
 * invoked again as a sub-agent (e.g. A → B → A), which would loop forever.
 */
class DelegationCycleException extends AtlasException
{
    /**
     * @param  array<int, string>  $chain  The delegation chain up to the repeated agent.
     */
    public function __construct(
        public readonly string $agent,
        public readonly array $chain,
    ) {
        $path = implode(' → ', [...$chain, $agent]);

        parent::__construct(
            "Sub-agent delegation cycle detected: {$path}. An agent cannot delegate back to one already in the chain."
        );
    }
}
