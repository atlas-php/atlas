<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected function isPostgres(): bool
    {
        return Schema::getConnection()->getDriverName() === 'pgsql';
    }

    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamps();

            if ($this->isPostgres()) {
                $dimensions = config('atlas.embeddings.dimensions', 1536);
                $table->vector('embedding', $dimensions)->nullable();
                $table->timestamp('embedding_at')->nullable();
            }
        });

        if ($this->isPostgres()) {
            DB::statement(
                'CREATE INDEX notes_embedding_idx ON notes USING hnsw (embedding vector_cosine_ops)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
