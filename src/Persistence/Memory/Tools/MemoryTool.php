<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Memory\Tools;

use Atlasphp\Atlas\Persistence\Memory\MemoryContext;
use Atlasphp\Atlas\Persistence\Memory\MemoryModelService;
use Atlasphp\Atlas\Tools\Tool;

/**
 * Abstract base class for memory tools.
 *
 * Provides shared constructor injection and context guard used by all
 * memory tools (recall, search, remember).
 */
abstract class MemoryTool extends Tool
{
    public function __construct(
        protected readonly MemoryModelService $service,
        protected readonly MemoryContext $memoryContext,
    ) {}

    /**
     * Guard against unconfigured memory context.
     *
     * Returns an error message if the context is not configured, null otherwise.
     */
    protected function guardContext(): ?string
    {
        if (! $this->memoryContext->isConfigured()) {
            return 'Memory context is not configured. Call MemoryContext::configure() before running the agent.';
        }

        return null;
    }
}
