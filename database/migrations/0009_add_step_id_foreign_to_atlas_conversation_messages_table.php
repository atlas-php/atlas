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
        Schema::table($this->tableName('conversation_messages'), function (Blueprint $table) {
            $table->foreign('step_id')
                ->references('id')
                ->on($this->tableName('execution_steps'))
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table($this->tableName('conversation_messages'), function (Blueprint $table) {
            $table->dropForeign(['step_id']);
        });
    }
};
