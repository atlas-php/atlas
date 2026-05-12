<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected function tableName(string $name): string
    {
        return config('atlas.persistence.table_prefix', 'atlas_').$name;
    }

    protected function isPostgres(): bool
    {
        return Schema::getConnection()->getDriverName() === 'pgsql';
    }

    public function up(): void
    {
        Schema::create($this->tableName('chunks'), function (Blueprint $table) {
            $table->id();
            $table->morphs('chunkable');
            $table->unsignedInteger('ord');
            $table->text('heading_path')->nullable();
            $table->text('content');
            $table->char('content_hash', 32);
            $table->unsignedInteger('token_count');
            $table->string('embedding_model', 64);
            $table->timestamp('embedded_at');
            $table->timestamps();

            if ($this->isPostgres()) {
                $dimensions = config('atlas.embeddings.dimensions', 1536);
                $table->vector('embedding', $dimensions);
            }

            $table->index(['chunkable_type', 'content_hash']);
        });

        if ($this->isPostgres()) {
            $table = $this->tableName('chunks');
            DB::statement(
                "CREATE INDEX {$table}_embedding_idx ON {$table} USING hnsw (embedding vector_cosine_ops)"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tableName('chunks'));
    }
};
