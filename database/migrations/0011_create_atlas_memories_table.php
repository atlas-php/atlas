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
        Schema::create($this->tableName('memories'), function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('memoryable');
            $table->string('agent')->nullable()->index();
            $table->string('type', 50)->index();
            $table->string('namespace', 100)->nullable()->index();
            $table->string('key')->nullable();
            $table->text('content');
            $table->float('importance')->default(0.5);
            $table->string('source', 100)->nullable()->index();
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $tableName = $this->tableName('memories');

        if ($this->isPostgres()) {
            $dimensions = config('atlas.embeddings.dimensions',
                config('atlas.persistence.embedding_dimensions', 1536)
            );

            // Partial unique index — only enforced on active document memories.
            // Excludes: atomic memories (key=null) and soft-deleted rows (deleted_at IS NOT NULL).
            DB::statement(
                "CREATE UNIQUE INDEX {$tableName}_document_unique ON {$tableName} "
                .'(memoryable_type, memoryable_id, agent, type, key) WHERE key IS NOT NULL AND deleted_at IS NULL'
            );

            Schema::table($tableName, function (Blueprint $table) use ($dimensions) {
                $table->vector('embedding', $dimensions)->nullable();
                $table->timestamp('embedding_at')->nullable();
            });

            DB::statement(
                "CREATE INDEX {$tableName}_embedding_idx ON {$tableName} "
                .'USING hnsw (embedding vector_cosine_ops)'
            );
        } else {
            // Non-PostgreSQL: standard unique constraint.
            // MySQL/MariaDB treat NULLs as distinct in unique constraints (InnoDB).
            // SQLite also treats NULLs as distinct per SQL standard.
            // Atomic memories (key=null) will not collide.
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->unique(
                    ['memoryable_type', 'memoryable_id', 'agent', 'type', 'key'],
                    $tableName.'_document_unique'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tableName('memories'));
    }
};
