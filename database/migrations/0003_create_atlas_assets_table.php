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
        Schema::create($this->tableName('assets'), function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('mime_type', 100)->nullable();
            $table->string('filename', 255);
            $table->string('original_filename', 255)->nullable();
            $table->string('path', 500);
            $table->string('disk', 50);
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->text('description')->nullable();
            $table->nullableMorphs('author');
            $table->string('agent', 255)->nullable();
            $table->unsignedBigInteger('execution_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Conditional vector column — PostgreSQL only
            if ($this->isPostgres()) {
                $dimensions = config('atlas.persistence.embedding_dimensions', 1536);
                $table->vector('embedding', $dimensions)->nullable();
                $table->timestamp('embedding_at')->nullable();
            }

            $table->index('type');
            $table->index('content_hash');
            $table->index('agent');
            $table->index('execution_id');
        });

        // Add HNSW vector index — PostgreSQL only
        if ($this->isPostgres()) {
            $table = $this->tableName('assets');
            DB::statement(
                "CREATE INDEX {$table}_embedding_idx ON {$table} USING hnsw (embedding vector_cosine_ops)"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tableName('assets'));
    }
};
