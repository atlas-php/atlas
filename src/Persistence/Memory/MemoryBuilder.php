<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Memory;

use Atlasphp\Atlas\Persistence\Models\Memory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Class MemoryBuilder
 *
 * Fluent facade API for memory operations. Returned by Atlas::memory().
 * Scoping methods return cloned instances for immutability.
 * All operations delegate to MemoryModelService with the scoped parameters.
 */
class MemoryBuilder
{
    protected ?Model $owner = null;

    protected ?string $agent = null;

    protected ?string $namespace = null;

    public function __construct(
        protected readonly MemoryModelService $service,
    ) {}

    // ─── Scoping (returns cloned instance — immutable) ──────────

    public function for(Model $owner): static
    {
        $clone = clone $this;
        $clone->owner = $owner;

        return $clone;
    }

    public function agent(string $agent): static
    {
        $clone = clone $this;
        $clone->agent = $agent;

        return $clone;
    }

    public function namespace(string $namespace): static
    {
        $clone = clone $this;
        $clone->namespace = $namespace;

        return $clone;
    }

    // ─── Remember ───────────────────────────────────────────────

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function remember(
        string $content,
        string $type = 'atomic',
        ?string $namespace = null,
        ?string $key = null,
        float $importance = 0.5,
        ?string $source = null,
        ?Carbon $expiresAt = null,
        ?array $metadata = null,
    ): Memory {
        return $this->service->remember(
            owner: $this->owner,
            content: $content,
            type: $type,
            namespace: $namespace ?? $this->namespace,
            key: $key,
            agent: $this->agent,
            importance: $importance,
            source: $source,
            expiresAt: $expiresAt,
            metadata: $metadata,
        );
    }

    // ─── Forget ─────────────────────────────────────────────────

    /**
     * Soft-delete a specific memory by ID.
     */
    public function forget(int $id): bool
    {
        return $this->service->forget($id);
    }

    /**
     * Soft-delete memories matching the given criteria.
     */
    public function forgetWhere(
        ?string $type = null,
        ?string $namespace = null,
        ?string $key = null,
    ): int {
        return $this->service->forgetFor(
            owner: $this->owner,
            type: $type,
            namespace: $namespace ?? $this->namespace,
            key: $key,
            agent: $this->agent,
        );
    }

    public function forgetExpired(): int
    {
        return $this->service->forgetExpired();
    }

    // ─── Search ─────────────────────────────────────────────────

    /**
     * @return Collection<int, Memory>
     */
    public function search(
        string $query,
        ?string $type = null,
        ?string $namespace = null,
        float $minSimilarity = 0.5,
        int $limit = 10,
    ): Collection {
        return $this->service->search(
            owner: $this->owner,
            query: $query,
            type: $type,
            namespace: $namespace ?? $this->namespace,
            agent: $this->agent,
            minSimilarity: $minSimilarity,
            limit: $limit,
        );
    }

    // ─── Recall ─────────────────────────────────────────────────

    public function recall(string $type, ?string $key = null): ?Memory
    {
        return $this->service->recall(
            owner: $this->owner,
            type: $type,
            key: $key,
            agent: $this->agent,
        );
    }

    /**
     * @param  array<int, string>  $types
     * @return Collection<int, Memory>
     */
    public function recallMany(array $types): Collection
    {
        return $this->service->recallMany(
            owner: $this->owner,
            types: $types,
            agent: $this->agent,
        );
    }

    // ─── Query (escape hatch) ───────────────────────────────────

    /**
     * @return Builder<Memory>
     */
    public function query(): Builder
    {
        $builder = $this->service->query($this->owner);

        if ($this->agent !== null) {
            $builder->forAgent($this->agent);
        }

        if ($this->namespace !== null) {
            $builder->inNamespace($this->namespace);
        }

        return $builder;
    }

    // ─── Maintenance ────────────────────────────────────────────

    public function decay(Carbon $olderThan, float $factor = 0.8): int
    {
        return $this->service->decay($this->owner, $olderThan, $factor);
    }
}
