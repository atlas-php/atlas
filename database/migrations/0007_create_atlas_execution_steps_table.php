<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::create($this->tableName('execution_steps'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('execution_id')
                ->constrained($this->tableName('executions'))
                ->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->unsignedTinyInteger('status')->default(0);
            $table->text('content')->nullable();
            $table->text('reasoning')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->string('finish_reason', 30)->nullable();
            $table->text('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index('execution_id');
            $table->index('status');
            $table->index(['execution_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tableName('execution_steps'));
    }
};
