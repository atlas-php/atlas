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
        Schema::create($this->tableName('execution_tool_calls'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('execution_id')
                ->constrained($this->tableName('executions'))
                ->cascadeOnDelete();
            $table->foreignId('step_id')
                ->constrained($this->tableName('execution_steps'))
                ->cascadeOnDelete();
            $table->string('tool_call_id', 100);
            $table->string('name', 100);
            $table->string('type', 20);
            $table->unsignedTinyInteger('status')->default(0);
            $table->json('arguments')->nullable();
            $table->text('result')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('execution_id');
            $table->index('step_id');
            $table->index('status');
            $table->index('name');
            $table->index('tool_call_id');
            $table->index('asset_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tableName('execution_tool_calls'));
    }
};
