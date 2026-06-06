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
        Schema::table($this->tableName('execution_tool_calls'), function (Blueprint $table) {
            // Citations (url_citation / web_search_result_location, etc.) the
            // provider returned for this provider-tool action. Attached to the
            // search/fetch action that produced them.
            $table->json('annotations')->nullable()->after('result');
        });
    }

    public function down(): void
    {
        Schema::table($this->tableName('execution_tool_calls'), function (Blueprint $table) {
            $table->dropColumn('annotations');
        });
    }
};
