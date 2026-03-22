<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Shared authorship support for models with author_type, author_id, and agent columns.
 *
 * Provides polymorphic author relationship, authorship checks, and query scopes.
 */
trait HasAuthor
{
    /** @return MorphTo<Model, $this> */
    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    public function isHumanAuthored(): bool
    {
        return $this->author_type !== null && $this->author_id !== null;
    }

    public function isAgentAuthored(): bool
    {
        return $this->agent !== null;
    }

    public function authorName(): ?string
    {
        if ($this->agent !== null) {
            return $this->agent;
        }

        if ($this->isHumanAuthored()) {
            $author = $this->author;

            return $author !== null && isset($author->name) ? (string) $author->name : null;
        }

        return null;
    }

    /** @param Builder<static> $query */
    public function scopeByAuthor(Builder $query, Model $author): void
    {
        $query->where('author_type', $author->getMorphClass())
            ->where('author_id', $author->getKey());
    }

    /** @param Builder<static> $query */
    public function scopeByAgent(Builder $query, string $agentKey): void
    {
        $query->where('agent', $agentKey);
    }
}
