<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Models;

use Atlasphp\Atlas\Database\Factories\ExecutionFactory;
use Atlasphp\Atlas\Persistence\Concerns\HasAtlasTable;
use Atlasphp\Atlas\Persistence\Enums\ExecutionStatus;
use Atlasphp\Atlas\Persistence\Enums\ExecutionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Class Execution
 *
 * Tracks a single AI execution lifecycle including provider calls, token usage,
 * and timing. Aggregates steps and tool calls for full observability.
 *
 * @property int $id
 * @property int|null $conversation_id
 * @property int|null $message_id
 * @property int|null $voice_call_id
 * @property string|null $agent
 * @property ExecutionType $type
 * @property int|null $asset_id
 * @property string $provider
 * @property string $model
 * @property ExecutionStatus $status
 * @property int $total_input_tokens
 * @property int $total_output_tokens
 * @property string|null $error
 * @property array<mixed>|null $metadata
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property int|null $duration_ms
 */
class Execution extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasAtlasTable, HasFactory;

    protected static function newFactory(): ExecutionFactory
    {
        return ExecutionFactory::new();
    }

    protected $table = 'executions';

    protected $fillable = [
        'conversation_id',
        'message_id',
        'voice_call_id',
        'agent',
        'type',
        'asset_id',
        'provider',
        'model',
        'status',
        'total_input_tokens',
        'total_output_tokens',
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
            'total_input_tokens' => 'integer',
            'total_output_tokens' => 'integer',
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
        $model = config('atlas.persistence.models.conversation', Conversation::class);

        return $this->belongsTo($model);
    }

    /** @return BelongsTo<Message, $this> */
    public function triggerMessage(): BelongsTo
    {
        /** @var class-string<Message> $model */
        $model = config('atlas.persistence.models.message', Message::class);

        return $this->belongsTo($model, 'message_id');
    }

    /** @return BelongsTo<VoiceCall, $this> */
    public function voiceCall(): BelongsTo
    {
        /** @var class-string<VoiceCall> $model */
        $model = config('atlas.persistence.models.voice_call', VoiceCall::class);

        return $this->belongsTo($model);
    }

    /** @return HasMany<ExecutionStep, $this> */
    public function steps(): HasMany
    {
        /** @var class-string<ExecutionStep> $model */
        $model = config('atlas.persistence.models.execution_step', ExecutionStep::class);

        return $this->hasMany($model)->orderBy('sequence');
    }

    /** @return HasMany<ExecutionToolCall, $this> */
    public function toolCalls(): HasMany
    {
        /** @var class-string<ExecutionToolCall> $model */
        $model = config('atlas.persistence.models.execution_tool_call', ExecutionToolCall::class);

        return $this->hasMany($model);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        /** @var class-string<Asset> $model */
        $model = config('atlas.persistence.models.asset', Asset::class);

        return $this->belongsTo($model);
    }

    // ─── Voice Execution Lifecycle ──────────────────────────────

    /**
     * Complete a voice execution by execution ID.
     *
     * Uses atomic update guarded by status=Processing to prevent race
     * conditions when transcript and close requests arrive concurrently.
     * No-op if the execution is not found or already completed.
     *
     * @param  array<string, mixed>|null  $extraMeta
     */
    public static function completeVoiceExecution(int $executionId, ?array $extraMeta = null): ?static
    {
        /** @var class-string<static> $model */
        $model = config('atlas.persistence.models.execution', static::class);

        /** @var static|null $execution */
        $execution = $model::where('id', $executionId)
            ->where('status', ExecutionStatus::Processing)
            ->first();

        if ($execution === null) {
            return null;
        }

        $durationMs = $execution->started_at !== null
            ? (int) abs(now()->diffInMilliseconds($execution->started_at))
            : null;

        // The WHERE status=Processing guard makes this race-safe.
        $affected = DB::transaction(function () use ($model, $execution, $durationMs, $extraMeta): int {
            $affected = $model::where('id', $execution->id)
                ->where('status', ExecutionStatus::Processing)
                ->update([
                    'status' => ExecutionStatus::Completed,
                    'completed_at' => now(),
                    'duration_ms' => $durationMs,
                ]);

            if ($affected === 0) {
                return 0;
            }

            if ($extraMeta !== null) {
                $metadata = array_merge($execution->metadata ?? [], $extraMeta);
                $model::where('id', $execution->id)->update(['metadata' => $metadata]);
            }

            return $affected;
        });

        if ($affected === 0) {
            return null;
        }

        $execution->refresh();

        return $execution;
    }

    // ─── Lifecycle ──────────────────────────────────────────────

    /**
     * Transition pending → queued. Called when dispatched to queue.
     */
    public function markQueued(): void
    {
        $this->update(['status' => ExecutionStatus::Queued]);
    }

    public function markCompleted(?int $durationMs): void
    {
        $tokens = $this->aggregateStepTokens();

        $this->update([
            'status' => ExecutionStatus::Completed,
            'completed_at' => now(),
            'duration_ms' => $durationMs,
            'total_input_tokens' => $tokens->input,
            'total_output_tokens' => $tokens->output,
        ]);
    }

    public function markFailed(string $error, ?int $durationMs): void
    {
        $tokens = $this->aggregateStepTokens();

        $this->update([
            'status' => ExecutionStatus::Failed,
            'completed_at' => now(),
            'duration_ms' => $durationMs,
            'error' => $error,
            'total_input_tokens' => $tokens->input,
            'total_output_tokens' => $tokens->output,
        ]);
    }

    /**
     * Aggregate input and output token counts from all steps in a single query.
     *
     * @return object{input: int, output: int}
     */
    private function aggregateStepTokens(): object
    {
        return $this->steps()
            ->reorder()
            ->selectRaw('COALESCE(SUM(input_tokens), 0) as input, COALESCE(SUM(output_tokens), 0) as output')
            ->first() ?? (object) ['input' => 0, 'output' => 0];
    }

    // ─── Accessors ──────────────────────────────────────────────

    public function getTotalTokensAttribute(): int
    {
        return $this->total_input_tokens + $this->total_output_tokens;
    }

    // ─── Scopes ─────────────────────────────────────────────────

    /** @param Builder<static> $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', ExecutionStatus::Pending);
    }

    /** @param Builder<static> $query */
    public function scopeQueued(Builder $query): void
    {
        $query->where('status', ExecutionStatus::Queued);
    }

    /** @param Builder<static> $query */
    public function scopeProcessing(Builder $query): void
    {
        $query->where('status', ExecutionStatus::Processing);
    }

    /** @param Builder<static> $query */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', ExecutionStatus::Completed);
    }

    /** @param Builder<static> $query */
    public function scopeFailed(Builder $query): void
    {
        $query->where('status', ExecutionStatus::Failed);
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
        $query->whereNotNull('asset_id');
    }
}
