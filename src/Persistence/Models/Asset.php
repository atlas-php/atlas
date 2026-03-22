<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Models;

use Atlasphp\Atlas\Database\Factories\AssetFactory;
use Atlasphp\Atlas\Persistence\Concerns\HasAtlasTable;
use Atlasphp\Atlas\Persistence\Concerns\HasAuthor;
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
 * Represents a stored file (image, audio, video, document, etc.) with content-hash deduplication
 * and optional vector embeddings for semantic search. Assets exist independently of messages and
 * can be referenced by multiple messages across different conversations.
 *
 * @property AssetType $type
 * @property string|null $mime_type
 * @property string $filename
 * @property string|null $original_filename
 * @property string $path
 * @property string $disk
 * @property int|null $size_bytes
 * @property string|null $content_hash
 * @property string|null $description
 * @property array<mixed>|null $embedding
 * @property Carbon|null $embedding_at
 * @property array<mixed>|null $metadata
 * @property string|null $author_type
 * @property int|null $author_id
 * @property string|null $agent
 * @property int|null $execution_id
 */
class Asset extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasAtlasTable, HasAuthor, HasFactory, SoftDeletes;

    protected static function newFactory(): AssetFactory
    {
        return AssetFactory::new();
    }

    protected $table = 'assets';

    protected $fillable = [
        'type',
        'mime_type',
        'filename',
        'original_filename',
        'path',
        'disk',
        'size_bytes',
        'content_hash',
        'description',
        'embedding',
        'embedding_at',
        'metadata',
        'author_type',
        'author_id',
        'agent',
        'execution_id',
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

    /** @return HasMany<MessageAttachment, $this> */
    public function attachments(): HasMany
    {
        /** @var class-string<MessageAttachment> $model */
        $model = config('atlas.persistence.models.message_attachment', MessageAttachment::class);

        return $this->hasMany($model);
    }

    /** @return BelongsTo<Execution, $this> */
    public function execution(): BelongsTo
    {
        /** @var class-string<Execution> $model */
        $model = config('atlas.persistence.models.execution', Execution::class);

        return $this->belongsTo($model);
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

    // ─── Scopes (byAuthor + byAgent provided by HasAuthor) ─────

    /** @param Builder<static> $query */
    public function scopeForExecution(Builder $query, int $executionId): void
    {
        $query->where('execution_id', $executionId);
    }
}
