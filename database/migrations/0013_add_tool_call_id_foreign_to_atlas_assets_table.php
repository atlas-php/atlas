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
        Schema::table($this->tableName('assets'), function (Blueprint $table) {
            $table->foreign('tool_call_id')
                ->references('id')
                ->on($this->tableName('execution_tool_calls'))
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table($this->tableName('assets'), function (Blueprint $table) {
            $table->dropForeign(['tool_call_id']);
        });
    }
};
