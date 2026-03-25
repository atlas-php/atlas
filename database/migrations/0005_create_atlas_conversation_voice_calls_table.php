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

    public function up(): void
    {
        Schema::create($this->tableName('conversation_voice_calls'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                ->nullable()
                ->constrained($this->tableName('conversations'))
                ->nullOnDelete();
            $table->unsignedBigInteger('execution_id')->nullable(); // FK added in 0012 after executions exists
            $table->string('voice_session_id', 100)->unique();
            $table->nullableMorphs('owner');
            $table->string('agent', 255)->nullable();
            $table->string('provider', 50);
            $table->string('model', 100);
            $table->string('status', 20)->default('active');
            $table->json('transcript')->nullable();
            $table->text('summary')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('conversation_id');
            $table->index('execution_id');
            $table->index('agent');
            $table->index('status');
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tableName('conversation_voice_calls'));
    }
};
