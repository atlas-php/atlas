<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Models;

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Database\Factories\ExecutionFactory;
use Atlasphp\Atlas\Persistence\Concerns\HasAtlasTable;
use Atlasphp\Atlas\Persistence\Concerns\HasExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Atlasphp\Atlas\Responses\Usage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Class Execution
 *
 * Tracks a single AI execution lifecycle including provider calls, token usage,
 * and timing. Aggregates steps and tool calls for full observability.
 *
 * @property int $id
 * @property int|null $conversation_id
 * @property string|null $agent
 * @property ExecutionType $type
 * @property string $provider
 * @property string $model
 * @property ExecutionStatus $status
 * @property array<string, int>|null $usage
 * @property string|null $error
 * @property array<mixed>|null $metadata
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property int|null $duration_ms
 */
class Execution extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasAtlasTable, HasExecutionStatus, HasFactory;

    protected static function newFactory(): ExecutionFactory
    {
        return ExecutionFactory::new();
    }

    protected $table = 'executions';

    protected $fillable = [
        'conversation_id',
        'agent',
        'type',
        'provider',
        'model',
        'status',
        'usage',
        'error',
        'metadata',
        'started_at',
        'completed_at',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'type' => ExecutionType::class,
            'status' => ExecutionStatus::class,
            'usage' => 'array',
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'duration_ms' => 'integer',
        ];
    }

    // ─── Relationships ──────────────────────────────────────────

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        /** @var class-string<Conversation> $model */
        $model = app(AtlasConfig::class)->model('conversation', Conversation::class);

        return $this->belongsTo($model);
    }

    /** @return HasOne<ConversationMessage, $this> */
    public function message(): HasOne
    {
        /** @var class-string<ConversationMessage> $model */
        $model = app(AtlasConfig::class)->model('conversation_message', ConversationMessage::class);

        return $this->hasOne($model, 'execution_id');
    }

    /** @return HasOne<VoiceCall, $this> */
    public function voiceCall(): HasOne
    {
        /** @var class-string<VoiceCall> $model */
        $model = app(AtlasConfig::class)->model('voice_call', VoiceCall::class);

        return $this->hasOne($model, 'execution_id');
    }

    /** @return HasMany<ExecutionStep, $this> */
    public function steps(): HasMany
    {
        /** @var class-string<ExecutionStep> $model */
        $model = app(AtlasConfig::class)->model('execution_step', ExecutionStep::class);

        return $this->hasMany($model)->orderBy('sequence');
    }

    /** @return HasMany<ExecutionToolCall, $this> */
    public function toolCalls(): HasMany
    {
        /** @var class-string<ExecutionToolCall> $model */
        $model = app(AtlasConfig::class)->model('execution_tool_call', ExecutionToolCall::class);

        return $this->hasMany($model);
    }

    /** @return HasMany<Asset, $this> */
    public function assets(): HasMany
    {
        /** @var class-string<Asset> $model */
        $model = app(AtlasConfig::class)->model('asset', Asset::class);

        return $this->hasMany($model);
    }

    // ─── Lifecycle ──────────────────────────────────────────────

    /**
     * Transition pending → queued. Called when dispatched to queue.
     */
    public function markQueued(): void
    {
        $this->update(['status' => ExecutionStatus::Queued]);
    }

    public function markCompleted(?int $durationMs, ?Usage $usage = null): void
    {
        $this->update([
            'status' => ExecutionStatus::Completed,
            'completed_at' => now(),
            'duration_ms' => $durationMs,
            'usage' => $usage?->toArray(),
        ]);
    }

    public function markFailed(string $error, ?int $durationMs, ?Usage $usage = null): void
    {
        $this->update([
            'status' => ExecutionStatus::Failed,
            'completed_at' => now(),
            'duration_ms' => $durationMs,
            'error' => $error,
            'usage' => $usage?->toArray(),
        ]);
    }

    // ─── Accessors ──────────────────────────────────────────────

    /**
     * Get the usage as a Usage DTO.
     */
    public function getUsageObject(): Usage
    {
        return Usage::fromArray($this->getUsageAttribute());
    }

    /**
     * Get usage with camelCase keys for API responses.
     *
     * @return array<string, int>|null
     */
    public function usage(): ?array
    {
        $usage = $this->getUsageAttribute();

        if ($usage === null) {
            return null;
        }

        $formatted = [];

        foreach ($usage as $key => $value) {
            $formatted[Str::camel($key)] = $value;
        }

        return $formatted;
    }

    /**
     * Raw usage array from the database.
     *
     * @return array<string, int>|null
     */
    public function getUsageAttribute(): ?array
    {
        $value = $this->attributes['usage'] ?? null;

        if ($value === null) {
            return null;
        }

        return is_array($value) ? $value : json_decode($value, true);
    }

    public function getTotalTokensAttribute(): int
    {
        $usage = $this->getUsageAttribute() ?? [];

        return ($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0);
    }

    // ─── Scopes ─────────────────────────────────────────────────

    /** @param Builder<static> $query */
    public function scopeQueued(Builder $query): void
    {
        $query->where('status', ExecutionStatus::Queued);
    }

    /** @param Builder<static> $query */
    public function scopeForAgent(Builder $query, string $agent): void
    {
        $query->where('agent', $agent);
    }

    /** @param Builder<static> $query */
    public function scopeForProvider(Builder $query, string $provider): void
    {
        $query->where('provider', $provider);
    }

    /** @param Builder<static> $query */
    public function scopeOfType(Builder $query, ExecutionType $type): void
    {
        $query->where('type', $type);
    }

    /** @param Builder<static> $query */
    public function scopeProducedAssets(Builder $query): void
    {
        $query->whereHas('assets');
    }
}
