<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Models;

use Atlasphp\Atlas\Persistence\Concerns\HasAtlasTable;
use Atlasphp\Atlas\Persistence\Concerns\HasOwner;
use Atlasphp\Atlas\Persistence\Enums\VoiceCallStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Class VoiceCall
 *
 * Represents a voice session with its complete transcript. Voice calls are
 * isolated from the messages table — the transcript is stored as a JSON array
 * of turns. Consumers can listen for VoiceCallCompleted to create summaries,
 * embed into memory, or inject context messages into the conversation.
 *
 * @property int $id
 * @property int|null $conversation_id
 * @property string $voice_session_id
 * @property string|null $owner_type
 * @property int|null $owner_id
 * @property string|null $agent
 * @property string $provider
 * @property string $model
 * @property VoiceCallStatus $status
 * @property array<int, array{role: string, content: string}>|null $transcript
 * @property string|null $summary
 * @property int|null $duration_ms
 * @property array<mixed>|null $metadata
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 */
class VoiceCall extends Model
{
    use HasAtlasTable, HasOwner;

    protected $table = 'voice_calls';

    protected $fillable = [
        'conversation_id',
        'voice_session_id',
        'owner_type',
        'owner_id',
        'agent',
        'provider',
        'model',
        'status',
        'transcript',
        'summary',
        'duration_ms',
        'metadata',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => VoiceCallStatus::class,
            'transcript' => 'array',
            'metadata' => 'array',
            'duration_ms' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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

    /** @return HasMany<Execution, $this> */
    public function executions(): HasMany
    {
        /** @var class-string<Execution> $model */
        $model = config('atlas.persistence.models.execution', Execution::class);

        return $this->hasMany($model, 'voice_call_id');
    }

    // ─── Lifecycle ──────────────────────────────────────────────

    /**
     * Update the transcript with the latest complete turns.
     * Atomic replace — no appending, no duplicates.
     *
     * @param  array<int, array{role: string, content: string}>  $turns
     */
    public function saveTranscript(array $turns): void
    {
        $this->update(['transcript' => $turns]);
    }

    /**
     * Mark the call as completed with the final transcript.
     *
     * @param  array<int, array{role: string, content: string}>  $turns
     */
    public function markCompleted(array $turns): void
    {
        $durationMs = $this->started_at !== null
            ? (int) abs(now()->diffInMilliseconds($this->started_at))
            : null;

        $this->update([
            'status' => VoiceCallStatus::Completed,
            'transcript' => $turns,
            'completed_at' => now(),
            'duration_ms' => $durationMs,
        ]);
    }

    public function markFailed(): void
    {
        $this->update([
            'status' => VoiceCallStatus::Failed,
            'completed_at' => now(),
        ]);
    }

    public function isActive(): bool
    {
        return $this->status === VoiceCallStatus::Active;
    }

    public function isCompleted(): bool
    {
        return $this->status === VoiceCallStatus::Completed;
    }

    // ─── Scopes ─────────────────────────────────────────────────

    /** @param Builder<static> $query */
    public function scopeForConversation(Builder $query, int $conversationId): void
    {
        $query->where('conversation_id', $conversationId);
    }

    /** @param Builder<static> $query */
    public function scopeForSession(Builder $query, string $sessionId): void
    {
        $query->where('voice_session_id', $sessionId);
    }

    /** @param Builder<static> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', VoiceCallStatus::Active);
    }

    /** @param Builder<static> $query */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', VoiceCallStatus::Completed);
    }
}
