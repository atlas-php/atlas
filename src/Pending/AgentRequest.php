<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Pending;

use RuntimeException;

/**
 * Stub for agent execution — accepts fluent calls but throws on terminal methods.
 *
 * Full implementation deferred to Phase 7 when the executor is ready.
 */
class AgentRequest
{
    public function __construct(
        protected readonly string $key,
    ) {}

    /**
     * Accept any fluent method call without error.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): static
    {
        return $this;
    }

    public function asText(): never
    {
        throw new RuntimeException('Agent execution requires Phase 7.');
    }

    public function asStream(): never
    {
        throw new RuntimeException('Agent execution requires Phase 7.');
    }

    public function asStructured(): never
    {
        throw new RuntimeException('Agent execution requires Phase 7.');
    }
}
