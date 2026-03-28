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
        // Enable pgvector extension for vector columns (PostgreSQL only, idempotent)
        if ($this->isPostgres()) {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        }

        Schema::create($this->tableName('conversation_messages'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                ->constrained($this->tableName('conversations'))
                ->cascadeOnDelete();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained($this->tableName('conversation_messages'))
                ->nullOnDelete();
            $table->unsignedBigInteger('execution_id')->nullable(); // FK added in 0011 after executions exists
            $table->unsignedBigInteger('step_id')->nullable(); // FK added in 0009 after execution_steps exists
            $table->nullableMorphs('owner');
            $table->string('agent', 255)->nullable();
            $table->string('role', 20);
            $table->string('status', 20)->default('delivered');
            $table->text('content')->nullable();
            $table->unsignedInteger('sequence')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamp('read_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Conditional vector column — PostgreSQL only
            if ($this->isPostgres()) {
                $dimensions = config('atlas.embeddings.dimensions', config('atlas.persistence.embedding_dimensions', 1536));
                $table->vector('embedding', $dimensions)->nullable();
                $table->timestamp('embedding_at')->nullable();
            }

            $table->index('conversation_id');
            $table->index('parent_id');
            $table->index('execution_id');
            $table->index('step_id');
            $table->index('role');
            $table->index('agent');
            $table->unique(['conversation_id', 'sequence']);
            $table->index(['conversation_id', 'status']);
            $table->index(['conversation_id', 'is_active']);
            // owner index already created by nullableMorphs('owner') above
        });

        // Add HNSW vector index — PostgreSQL only
        if ($this->isPostgres()) {
            $table = $this->tableName('conversation_messages');
            DB::statement(
                "CREATE INDEX {$table}_embedding_idx ON {$table} USING hnsw (embedding vector_cosine_ops)"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tableName('conversation_messages'));
    }
};
