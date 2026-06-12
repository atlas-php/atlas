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
        Schema::create($this->tableName('batch_results'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_job_id')
                ->constrained($this->tableName('batch_jobs'))->cascadeOnDelete();
            $table->string('custom_id', 191);
            $table->string('status', 20);
            $table->json('response')->nullable();
            $table->json('usage')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            // Unique: one result per (job, custom_id). Doubles as the trace-back
            // lookup index and hard-stops any duplicate insert on a retry.
            $table->unique(['batch_job_id', 'custom_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tableName('batch_results'));
    }
};
