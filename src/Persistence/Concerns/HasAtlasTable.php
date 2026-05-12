<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Concerns;

use Atlasphp\Atlas\AtlasConfig;

/**
 * Resolves the prefixed table name and database connection for persistence models.
 *
 * Every persistence model uses this trait to support the configurable
 * table prefix from atlas.persistence.table_prefix and the optional
 * connection override from atlas.persistence.connection.
 */
trait HasAtlasTable
{
    public function getTable(): string
    {
        $prefix = app(AtlasConfig::class)->tablePrefix;

        // Guard against double-prefixing when Eloquent's newInstance() copies
        // the already-prefixed table name via setTable($this->getTable()).
        if (str_starts_with($this->table, $prefix)) {
            return $this->table;
        }

        return $prefix.$this->table;
    }

    public function getConnectionName(): ?string
    {
        return app(AtlasConfig::class)->persistenceConnection ?? $this->connection;
    }
}
