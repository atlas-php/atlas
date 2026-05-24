<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Resolves a database-scoped discriminator for unique-job lock keys.
 *
 * Under DB-per-tenant multi-tenancy the framework repoints a connection at
 * each tenant's database, so per-database auto-increment IDs repeat across
 * tenants (every tenant has a row id 1, 2, 3…). ShouldBeUnique locks live in
 * a shared cache, so a key built from an id alone lets one tenant's job
 * suppress another tenant's same-id job — silently dropping the second.
 *
 * Including the connection's database name in the key scopes the lock to the
 * physical database. This is tenancy-package agnostic (it reads the resolved
 * connection, not any tenancy API); a single-database app simply gets a
 * constant segment and behaves exactly as before.
 */
trait ResolvesDatabaseScope
{
    /**
     * The database name backing the given connection (null = default).
     */
    protected function databaseScope(?string $connection = null): string
    {
        return (string) DB::connection($connection)->getDatabaseName();
    }
}
