<?php

declare(strict_types=1);

namespace Atlasphp\Atlas\Persistence\Models;

use Atlasphp\Atlas\AtlasConfig;
use Atlasphp\Atlas\Enums\BatchResultStatus;
use Atlasphp\Atlas\Persistence\Concerns\HasAtlasTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class BatchResult
 *
 * One per-line outcome of a batch job, keyed by the consumer's custom id so a
 * result maps straight back to the originating record.
 *
 * @property int $id
 * @property int $batch_job_id
 * @property string $custom_id
 * @property BatchResultStatus $status
 * @property array<string, mixed>|null $response
 * @property array<string, int>|null $usage
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BatchResult extends Model
{
    use HasAtlasTable;

    protected $table = 'batch_results';

    protected $fillable = [
        'batch_job_id',
        'custom_id',
        'status',
        'response',
        'usage',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'status' => BatchResultStatus::class,
            'response' => 'array',
            'usage' => 'array',
        ];
    }

    /** @return BelongsTo<BatchJob, $this> */
    public function batchJob(): BelongsTo
    {
        /** @var class-string<BatchJob> $model */
        $model = app(AtlasConfig::class)->model('batch_job', BatchJob::class);

        return $this->belongsTo($model);
    }
}
