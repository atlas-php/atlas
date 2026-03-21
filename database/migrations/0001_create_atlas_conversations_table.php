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
        Schema::create($this->tableName('conversations'), function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 255)->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->string('agent', 255)->nullable();
            $table->string('title', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_type', 'owner_id']);
            $table->index('agent');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tableName('conversations'));
    }
};
