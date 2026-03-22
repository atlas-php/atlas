<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Memory;

use Illuminate\Database\Eloquent\Model;

/**
 * Class MemoryContext
 *
 * Scoped service that holds the memory owner and agent key for the current
 * request. Configured by WireMemory middleware, consumed by memory tools.
 * Replaces meta-based context passing to keep memory fully isolated.
 */
class MemoryContext
{
    private ?Model $owner = null;

    private ?string $agentKey = null;

    private bool $configured = false;

    /**
     * Configure the memory context for this request.
     */
    public function configure(?Model $owner, ?string $agentKey): void
    {
        $this->owner = $owner;
        $this->agentKey = $agentKey;
        $this->configured = true;
    }

    /**
     * Get the memory owner for this request.
     */
    public function owner(): ?Model
    {
        return $this->owner;
    }

    /**
     * Get the agent key for this request.
     */
    public function agentKey(): ?string
    {
        return $this->agentKey;
    }

    /**
     * Whether the context has been configured for this request.
     */
    public function isConfigured(): bool
    {
        return $this->configured;
    }

    /**
     * Reset state for the next request.
     */
    public function reset(): void
    {
        $this->owner = null;
        $this->agentKey = null;
        $this->configured = false;
    }
}
