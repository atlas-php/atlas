<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Concerns;

/**
 * Resolves the prefixed table name for persistence models.
 *
 * Every persistence model uses this trait to support the configurable
 * table prefix from atlas.persistence.table_prefix.
 */
trait HasAtlasTable
{
    public function getTable(): string
    {
        $prefix = config('atlas.persistence.table_prefix', 'atlas_');

        return $prefix.$this->table;
    }
}
