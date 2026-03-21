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
        Schema::create($this->tableName('message_attachments'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')
                ->constrained($this->tableName('messages'))
                ->cascadeOnDelete();
            $table->foreignId('asset_id')
                ->constrained($this->tableName('assets'))
                ->cascadeOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('message_id');
            $table->index('asset_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tableName('message_attachments'));
    }
};
