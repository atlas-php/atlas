<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Concerns;

use Atlasphp\Atlas\Persistence\Models\Chunk;

/**
 * Resolves the chunks model class with consumer-override support.
 *
 * Used by services that read or write atlas_chunks rows. Honors the
 * persistence.models.chunk config key so consumers can substitute their
 * own Chunk subclass without subclassing the services.
 */
trait ResolvesChunkModel
{
    /**
     * @return class-string<Chunk>
     */
    protected function chunkModel(): string
    {
        /** @var class-string<Chunk> $class */
        $class = $this->config->model('chunk', Chunk::class);

        return $class;
    }
}
