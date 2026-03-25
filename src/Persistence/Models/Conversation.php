<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Models;

use Atlasphp\Atlas\Database\Factories\ConversationFactory;
use Atlasphp\Atlas\Persistence\Concerns\HasAtlasTable;
use Atlasphp\Atlas\Persistence\Enums\MessageStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Class Conversation
 *
 * Represents a persistent conversation between an owner (user, team, etc.) and an Atlas agent.
 * Owns messages and executions, supports polymorphic ownership and agent-scoped queries.
 *
 * @property int $id
 * @property string|null $owner_type
 * @property int|null $owner_id
 * @property string|null $agent
 * @property string|null $title
 * @property string|null $summary
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class Conversation extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasAtlasTable, HasFactory, SoftDeletes;

    protected static function newFactory(): ConversationFactory
    {
        return ConversationFactory::new();
    }

    protected $table = 'conversations';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'agent',
        'title',
        'summary',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    // ─── Relationships ──────────────────────────────────────────

    /** @return MorphTo<Model, $this> */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<ConversationMessage, $this> */
    public function messages(): HasMany
    {
        /** @var class-string<ConversationMessage> $model */
        $model = config('atlas.persistence.models.conversation_message', ConversationMessage::class);

        return $this->hasMany($model)->orderBy('sequence');
    }

    /** @return HasMany<Execution, $this> */
    public function executions(): HasMany
    {
        /** @var class-string<Execution> $model */
        $model = config('atlas.persistence.models.execution', Execution::class);

        return $this->hasMany($model);
    }

    // ─── Scopes ─────────────────────────────────────────────────

    /** @param Builder<static> $query */
    public function scopeForOwner(Builder $query, Model $owner): void
    {
        $query->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey());
    }

    /** @param Builder<static> $query */
    public function scopeForAgent(Builder $query, string $agent): void
    {
        $query->where('agent', $agent);
    }

    // ─── Helpers ────────────────────────────────────────────────

    /**
     * Get the last N active, delivered messages for conversation replay.
     * Excludes inactive siblings (retries) and queued messages.
     */
    /** @return Collection<int, ConversationMessage> */
    public function recentMessages(int $limit = 50): Collection
    {
        return $this->messages()
            ->where('is_active', true)
            ->where('status', MessageStatus::Delivered)
            ->latest('sequence')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * Get the next sequence number.
     */
    public function nextSequence(): int
    {
        return ($this->messages()->max('sequence') ?? -1) + 1;
    }
}
