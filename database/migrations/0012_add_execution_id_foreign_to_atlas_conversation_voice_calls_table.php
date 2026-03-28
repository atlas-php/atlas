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
        Schema::table($this->tableName('conversation_voice_calls'), function (Blueprint $table) {
            $table->foreign('execution_id')
                ->references('id')
                ->on($this->tableName('executions'))
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table($this->tableName('conversation_voice_calls'), function (Blueprint $table) {
            $table->dropForeign(['execution_id']);
        });
    }
};
