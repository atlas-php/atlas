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
        Schema::table($this->tableName('executions'), function (Blueprint $table) {
            // Self-referential parent for sub-agent delegation trees.
            $table->foreignId('parent_execution_id')
                ->nullable()
                ->after('conversation_id')
                ->constrained($this->tableName('executions'))
                ->nullOnDelete();

            // The delegating tool call that spawned this execution.
            $table->foreignId('parent_tool_call_id')
                ->nullable()
                ->after('parent_execution_id')
                ->constrained($this->tableName('execution_tool_calls'))
                ->nullOnDelete();

            // Delegation depth (0 = root agent run).
            $table->unsignedTinyInteger('depth')->default(0)->after('parent_tool_call_id');

            $table->index('parent_execution_id');
            $table->index('parent_tool_call_id');
        });
    }

    public function down(): void
    {
        Schema::table($this->tableName('executions'), function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_execution_id');
            $table->dropConstrainedForeignId('parent_tool_call_id');
            $table->dropColumn('depth');
        });
    }
};
