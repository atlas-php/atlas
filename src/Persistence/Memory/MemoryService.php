<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Memory;

use Atlasphp\Atlas\Persistence\Models\Memory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Class MemoryService
 *
 * Core service for memory CRUD, semantic search, direct recall, and maintenance.
 * All memory operations flow through this service — tools and the facade builder delegate here.
 */
class MemoryService
{
    // ─── Remember ───────────────────────────────────────────────

    /**
     * Store a memory.
     *
     * Atomic: leave key null — each call creates a new row.
     * Document: set key — soft-deletes existing, creates new (version history preserved).
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function remember(
        ?Model $owner,
        string $content,
        string $type = 'atomic',
        ?string $namespace = null,
        ?string $key = null,
        ?string $agent = null,
        float $importance = 0.5,
        ?string $source = null,
        ?Carbon $expiresAt = null,
        ?array $metadata = null,
    ): Memory {
        $model = $this->resolveModel();

        $attributes = [
            'memoryable_type' => $owner?->getMorphClass(),
            'memoryable_id' => $owner?->getKey(),
            'agent' => $agent,
            'type' => $type,
            'namespace' => $namespace,
            'content' => $content,
            'importance' => $importance,
            'source' => $source,
            'expires_at' => $expiresAt,
            'metadata' => $metadata,
        ];

        if ($key !== null) {
            $model::query()
                ->where('memoryable_type', $owner?->getMorphClass())
                ->where('memoryable_id', $owner?->getKey())
                ->where('agent', $agent)
                ->where('type', $type)
                ->where('key', $key)
                ->first()
                ?->delete();

            $attributes['key'] = $key;
        }

        return $model::create($attributes);
    }

    // ─── Forget ─────────────────────────────────────────────────

    /**
     * Soft-delete a memory by ID.
     */
    public function forget(int $id): bool
    {
        $memory = $this->resolveModel()::find($id);

        return $memory !== null && (bool) $memory->delete();
    }

    /**
     * Bulk soft-delete memories matching the given criteria.
     */
    public function forgetFor(
        ?Model $owner = null,
        ?string $type = null,
        ?string $namespace = null,
        ?string $key = null,
        ?string $agent = null,
    ): int {
        $query = $this->resolveModel()::query();

        if ($owner !== null) {
            $query->forOwner($owner);
        } else {
            $query->global();
        }

        if ($type !== null) {
            $query->ofType($type);
        }

        if ($namespace !== null) {
            $query->inNamespace($namespace);
        }

        if ($key !== null) {
            $query->where('key', $key);
        }

        if ($agent !== null) {
            $query->forAgent($agent);
        }

        return $query->delete();
    }

    /**
     * Force-delete all expired memories.
     */
    public function forgetExpired(): int
    {
        return $this->resolveModel()::expired()->forceDelete();
    }

    // ─── Search (semantic) ──────────────────────────────────────

    /**
     * Semantic search using the existing whereVectorSimilarTo() macro.
     *
     * @return Collection<int, Memory>
     */
    public function search(
        ?Model $owner,
        string $query,
        ?string $type = null,
        ?string $namespace = null,
        ?string $agent = null,
        float $minSimilarity = 0.5,
        int $limit = 10,
    ): Collection {
        $builder = $this->resolveModel()::query()
            ->active()
            ->whereVectorSimilarTo('embedding', $query, $minSimilarity);

        if ($owner !== null) {
            $builder->forOwner($owner);
        }

        if ($type !== null) {
            $builder->ofType($type);
        }

        if ($namespace !== null) {
            $builder->inNamespace($namespace);
        }

        if ($agent !== null) {
            $builder->forAgent($agent);
        }

        $results = $builder->limit($limit)->get();

        if ($results->isNotEmpty()) {
            $this->resolveModel()::whereIn('id', $results->pluck('id'))
                ->update(['last_accessed_at' => now()]);
        }

        return $results;
    }

    // ─── Recall (direct fetch) ──────────────────────────────────

    /**
     * Direct fetch by type and optional key. No semantic search.
     */
    public function recall(
        ?Model $owner,
        string $type,
        ?string $key = null,
        ?string $agent = null,
    ): ?Memory {
        $query = $this->resolveModel()::query()->active()->ofType($type);

        if ($owner !== null) {
            $query->forOwner($owner);
        } else {
            $query->global();
        }

        if ($key !== null) {
            $query->where('key', $key);
        }

        if ($agent !== null) {
            $query->forAgent($agent);
        }

        $memory = $query->latest()->first();

        if ($memory !== null) {
            $memory->update(['last_accessed_at' => now()]);
        }

        return $memory;
    }

    /**
     * Fetch memories of multiple types at once.
     *
     * @param  array<int, string>  $types
     * @return Collection<int, Memory>
     */
    public function recallMany(?Model $owner, array $types, ?string $agent = null): Collection
    {
        $query = $this->resolveModel()::query()->active()->whereIn('type', $types);

        if ($owner !== null) {
            $query->forOwner($owner);
        } else {
            $query->global();
        }

        if ($agent !== null) {
            $query->forAgent($agent);
        }

        $results = $query->get();

        if ($results->isNotEmpty()) {
            $this->resolveModel()::whereIn('id', $results->pluck('id'))
                ->update(['last_accessed_at' => now()]);
        }

        return $results;
    }

    // ─── Query (escape hatch) ───────────────────────────────────

    /**
     * Return a scoped query builder for advanced queries.
     *
     * @return Builder<Memory>
     */
    public function query(?Model $owner = null): Builder
    {
        $query = $this->resolveModel()::query()->active();

        if ($owner !== null) {
            $query->forOwner($owner);
        }

        return $query;
    }

    // ─── Maintenance ────────────────────────────────────────────

    /**
     * Update last_accessed_at for a memory.
     */
    public function touch(int $id): void
    {
        $this->resolveModel()::where('id', $id)->update(['last_accessed_at' => now()]);
    }

    /**
     * Decay importance of old memories by multiplying with a factor.
     *
     * @param  float  $factor  Must be between 0.0 and 1.0 inclusive
     */
    public function decay(?Model $owner, Carbon $olderThan, float $factor = 0.8): int
    {
        $safeFactor = max(0.0, min(1.0, $factor));

        $query = $this->resolveModel()::query()
            ->where('updated_at', '<', $olderThan)
            ->where('importance', '>', 0);

        if ($owner !== null) {
            $query->forOwner($owner);
        }

        return $query->update(['importance' => DB::raw('importance * '.((string) $safeFactor))]);
    }

    /**
     * Resolve the Memory model class, supporting config overrides.
     *
     * @return class-string<Memory>
     */
    public function resolveModel(): string
    {
        /** @var class-string<Memory> */
        return config('atlas.persistence.models.memory', Memory::class);
    }
}
