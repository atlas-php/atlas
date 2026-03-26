<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Models;

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Database\Factories\AssetFactory;
use Atlasphp\Atlas\Persistence\Concerns\HasAtlasTable;
use Atlasphp\Atlas\Persistence\Concerns\HasOwner;
use Atlasphp\Atlas\Persistence\Enums\AssetType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Class Asset
 *
 * Represents a stored file (image, audio, video, document, etc.) with optional vector embeddings
 * for semantic search. Assets exist independently of messages and can be referenced by multiple
 * messages across different conversations.
 *
 * @property AssetType $type
 * @property string|null $mime_type
 * @property string $filename
 * @property string $path
 * @property string $disk
 * @property int|null $size_bytes
 * @property string|null $description
 * @property array<mixed>|null $embedding
 * @property Carbon|null $embedding_at
 * @property array<mixed>|null $metadata
 * @property string|null $owner_type
 * @property int|null $owner_id
 * @property string|null $agent
 * @property int|null $execution_id
 * @property int|null $tool_call_id
 */
class Asset extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasAtlasTable, HasFactory, HasOwner, SoftDeletes;

    protected static function newFactory(): AssetFactory
    {
        return AssetFactory::new();
    }

    protected $table = 'assets';

    protected $fillable = [
        'type',
        'mime_type',
        'filename',
        'path',
        'disk',
        'size_bytes',
        'description',
        'embedding',
        'embedding_at',
        'metadata',
        'owner_type',
        'owner_id',
        'agent',
        'execution_id',
        'tool_call_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => AssetType::class,
            'size_bytes' => 'integer',
            'embedding' => 'array',
            'embedding_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    // ─── Relationships ──────────────────────────────────────────

    /** @return HasMany<ConversationMessageAsset, $this> */
    public function messageAssets(): HasMany
    {
        /** @var class-string<ConversationMessageAsset> $model */
        $model = app(AtlasConfig::class)->model('conversation_message_asset', ConversationMessageAsset::class);

        return $this->hasMany($model);
    }

    /** @return BelongsTo<Execution, $this> */
    public function execution(): BelongsTo
    {
        /** @var class-string<Execution> $model */
        $model = app(AtlasConfig::class)->model('execution', Execution::class);

        return $this->belongsTo($model);
    }

    /** @return BelongsTo<ExecutionToolCall, $this> */
    public function toolCall(): BelongsTo
    {
        /** @var class-string<ExecutionToolCall> $model */
        $model = app(AtlasConfig::class)->model('execution_tool_call', ExecutionToolCall::class);

        return $this->belongsTo($model, 'tool_call_id');
    }

    // ─── Helpers ────────────────────────────────────────────────

    /**
     * Get the file extension from the stored path.
     */
    public function extension(): string
    {
        return pathinfo($this->path, PATHINFO_EXTENSION) ?: 'bin';
    }

    /**
     * Get the proxy URL for this asset (e.g. /api/assets/1.png).
     * Consumers can override the prefix for custom routing.
     */
    public function url(string $prefix = '/api/assets'): string
    {
        return "{$prefix}/{$this->id}.{$this->extension()}";
    }

    /**
     * Determine if this asset is a media type (image, audio, or video).
     */
    public function isMedia(): bool
    {
        return $this->type->isMedia();
    }

    // ─── Scopes (byOwner + byAgent provided by HasOwner) ──────

    /** @param Builder<static> $query */
    public function scopeForExecution(Builder $query, int $executionId): void
    {
        $query->where('execution_id', $executionId);
    }
}
