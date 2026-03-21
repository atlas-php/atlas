<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Concerns;

use Atlasphp\Atlas\Persistence\Memory\MemoryConfig;

/**
 * Trait HasMemory
 *
 * Agent-level memory configuration. Opt-in by adding this trait to an agent class.
 * Override memory() to configure tools and variable documents.
 *
 * Atlas provides tools and variable wiring. Consumers own everything else —
 * what to extract, how to inject, what types to use.
 */
trait HasMemory
{
    public function memory(): MemoryConfig
    {
        return MemoryConfig::make();
    }
}
