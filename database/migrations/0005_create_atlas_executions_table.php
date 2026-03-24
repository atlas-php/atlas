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
        Schema::create($this->tableName('executions'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                ->nullable()
                ->constrained($this->tableName('conversations'))
                ->nullOnDelete();
            $table->foreignId('message_id')
                ->nullable()
                ->constrained($this->tableName('messages'))
                ->nullOnDelete();
            $table->foreignId('asset_id')
                ->nullable()
                ->constrained($this->tableName('assets'))
                ->nullOnDelete();
            $table->string('agent', 255)->nullable();
            $table->string('type', 30)->default('text');
            $table->string('voice_session_id', 100)->nullable();
            $table->string('provider', 50);
            $table->string('model', 100);
            $table->unsignedTinyInteger('status')->default(0);
            $table->unsignedInteger('total_input_tokens')->default(0);
            $table->unsignedInteger('total_output_tokens')->default(0);
            $table->text('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index('conversation_id');
            $table->index('message_id');
            $table->index('agent');
            $table->index('type');
            $table->index('voice_session_id');
            $table->index('provider');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tableName('executions'));
    }
};
