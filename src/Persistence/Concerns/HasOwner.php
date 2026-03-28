<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Shared ownership support for models with owner_type, owner_id, and agent columns.
 *
 * Provides polymorphic owner relationship, ownership checks, and query scopes.
 */
trait HasOwner
{
    /** @return MorphTo<Model, $this> */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function isHumanOwned(): bool
    {
        return $this->owner_type !== null && $this->owner_id !== null;
    }

    public function isAgentOwned(): bool
    {
        return $this->agent !== null;
    }

    public function ownerName(): ?string
    {
        if ($this->isHumanOwned()) {
            $owner = $this->owner;

            return $owner !== null && isset($owner->name) ? (string) $owner->name : null;
        }

        if ($this->agent !== null) {
            return $this->agent;
        }

        return null;
    }

    /** @param Builder<static> $query */
    public function scopeByOwner(Builder $query, Model $owner): void
    {
        $query->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey());
    }

    /** @param Builder<static> $query */
    public function scopeByAgent(Builder $query, string $agentKey): void
    {
        $query->where('agent', $agentKey);
    }
}
