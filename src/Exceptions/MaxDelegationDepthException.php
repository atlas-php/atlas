<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Exceptions;

/**
 * Thrown when sub-agent delegation exceeds the configured maximum depth.
 *
 * Guards against runaway nesting where agents delegate to agents without
 * bound. The limit is configurable via `atlas.agents.max_delegation_depth`.
 */
class MaxDelegationDepthException extends AtlasException
{
    public function __construct(
        public readonly int $limit,
        public readonly string $agent,
    ) {
        parent::__construct(
            "Sub-agent delegation exceeded the maximum depth of {$limit} while delegating to '{$agent}'. "
            .'Increase atlas.agents.max_delegation_depth or reduce nesting.'
        );
    }
}
