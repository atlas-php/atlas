<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Models;

use Atlasphp\Atlas\Database\Factories\MemoryFactory;
use Atlasphp\Atlas\Persistence\Concerns\HasAtlasTable;
use Atlasphp\Atlas\Persistence\Concerns\HasVectorEmbeddings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Class Memory
 *
 * Represents a persistent memory entry scoped to an owner and/or agent.
 * Supports atomic memories (individual facts) and document memories
 * (named blocks with upsert semantics via soft-delete versioning).
 *
 * @property int $id
 * @property string|null $memoryable_type
 * @property int|null $memoryable_id
 * @property string|null $agent
 * @property string $type
 * @property string|null $namespace
 * @property string|null $key
 * @property string $content
 * @property float $importance
 * @property string|null $source
 * @property Carbon|null $last_accessed_at
 * @property Carbon|null $expires_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class Memory extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasAtlasTable, HasFactory, HasVectorEmbeddings, SoftDeletes;

    protected static function newFactory(): MemoryFactory
    {
        return MemoryFactory::new();
    }

    protected $table = 'memories';

    protected $fillable = [
        'memoryable_type',
        'memoryable_id',
        'agent',
        'type',
        'namespace',
        'key',
        'content',
        'importance',
        'source',
        'last_accessed_at',
        'expires_at',
        'metadata',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'importance' => 'float',
            'last_accessed_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return array{column: string, source: string|array<int, string>}
     */
    public function embeddable(): array
    {
        return ['column' => 'embedding', 'source' => 'content'];
    }

    /**
     * Respect the persistence.memory_auto_embed config toggle.
     *
     * Overrides HasVectorEmbeddings::shouldGenerateEmbedding() to add
     * the memory-specific config check before the standard logic.
     */
    public function shouldGenerateEmbedding(): bool
    {
        if (! config('atlas.persistence.memory_auto_embed', true)) {
            return false;
        }

        if (! config('atlas.persistence.enabled', false)) {
            return false;
        }

        if (! $this->isDirty('content')) {
            return false;
        }

        return $this->getEmbeddableContent() !== '';
    }

    // ─── Relationships ──────────────────────────────────────────

    /** @return MorphTo<Model, $this> */
    public function memoryable(): MorphTo
    {
        return $this->morphTo();
    }

    // ─── Scopes ─────────────────────────────────────────────────

    /** @param Builder<static> $query */
    public function scopeForOwner(Builder $query, Model $owner): void
    {
        $query->where('memoryable_type', $owner->getMorphClass())
            ->where('memoryable_id', $owner->getKey());
    }

    /** @param Builder<static> $query */
    public function scopeGlobal(Builder $query): void
    {
        $query->whereNull('memoryable_type')->whereNull('memoryable_id');
    }

    /** @param Builder<static> $query */
    public function scopeForAgent(Builder $query, string $agent): void
    {
        $query->where('agent', $agent);
    }

    /** @param Builder<static> $query */
    public function scopeAgentAgnostic(Builder $query): void
    {
        $query->whereNull('agent');
    }

    /** @param Builder<static> $query */
    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    /** @param Builder<static> $query */
    public function scopeInNamespace(Builder $query, string $namespace): void
    {
        $query->where('namespace', $namespace);
    }

    /** @param Builder<static> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** @param Builder<static> $query */
    public function scopeExpired(Builder $query): void
    {
        $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }

    // ─── Helpers ────────────────────────────────────────────────

    public function isAtomic(): bool
    {
        return $this->key === null;
    }

    public function isDocument(): bool
    {
        return $this->key !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
